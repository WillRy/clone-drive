<?php

namespace App\Models;

use App\Jobs\UploadFileToCloudJob;
use App\Trait\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\NodeTrait;

class File extends Model
{
    use HasCreatorAndUpdater, HasFactory, NodeTrait, SoftDeletes;

    protected $fillable = ['name', 'is_folder', 'user_id', 'uploaded_on_cloud'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (! $model->parent) {
                return;
            }

            $model->path = (! $model->parent->isRoot() ? $model->parent->path.'/' : '').Str::slug($model->name);
        });
        // static::deleted(function(File $model) {
        //     if(!$model->is_folder) {
        //         Storage::delete($model->storage_path);
        //     }
        // });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(File::class, 'parent_id');
    }

    public function starred(): HasOne
    {
        return $this->hasOne(StarredFile::class, 'file_id', 'id')->where('user_id', auth()->id());
    }

    public function owner(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return $attributes['created_by'] === auth()->id() ? 'me' : $this->user->name;
            }
        );
    }

    public function isOwnedBy($userId): bool
    {
        return $this->created_by === $userId;
    }

    public function isRoot()
    {
        return $this->parent_id === null;
    }

    public function getFileSize()
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $power = $this->size > 0 ? floor(log($this->size, 1024)) : 0;

        return number_format($this->size / pow(1024, $power), 2, '.', ',').' '.$units[$power];
    }

    public function moveToTrash()
    {
        $this->deleted_at = now();

        return $this->save();
    }

    public function deleteForever()
    {
        $this->deleteFilesFromStorage([$this]);
        $this->forceDelete();
    }

    public static function getRoot(): File
    {
        return File::query()->where('created_by', auth()->id())->whereIsRoot()->first();
    }

    public function findFolderByPathOrFail(string $folder)
    {
        return File::query()
            ->where('created_by', auth()->id())
            ->where('path', $folder)
            ->firstOrFail();
    }

    public function getMyFiles(
        ?File $folder,
        ?string $search = null,
        bool $favourites = false
    ) {
        return File::query()
            ->with('starred')
            ->where('created_by', auth()->id())
            ->whereNotNull('parent_id')
            ->when($favourites, function ($query) {
                //this relation already filters by the authenticated user
                $query->whereHas('starred');
            })
            ->where(function ($query) use ($folder, $search) {
                if (empty($search)) {
                    $query->where('parent_id', $folder->id);
                } else {
                    $query->where('name', 'like', "%{$search}%");
                }
            })
            ->orderBy('is_folder', 'desc')
            ->orderBy('files.created_at', 'desc')
            ->orderBy('files.id', 'desc')
            ->paginate(20);
    }

    public function getTrash(
        ?string $search = null
    ) {
        return File::query()
            ->onlyTrashed()
            ->where('created_by', auth()->id())
            ->when(! empty($search), function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('is_folder', 'desc')
            ->orderBy('deleted_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    public function deleteFilesFromStorage(Collection|array $files)
    {
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->deleteFilesFromStorage($file->children);
            } else {
                if ($file->uploaded_on_cloud) {
                    Storage::delete($file->storage_path);
                } else {
                    Storage::disk('local')->delete($file->storage_path);
                }
            }
        }
    }

    public static function getSharedWithMe(?string $search = null)
    {
        return File::query()
            ->select('files.*')
            ->join('file_shares', function ($query) {
                $query
                    ->on('file_shares.file_id', '=', 'files.id')
                    ->where('file_shares.user_id', auth()->id());
            })
            ->when(! empty($search), function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('file_shares.created_at', 'desc')
            ->orderBy('files.id', 'desc')
            ->orderBy('is_folder', 'desc');
    }

    public static function getSharedByMe(?string $search = null)
    {
        return File::query()
            ->select('files.*')
            ->join('file_shares', function ($query) {
                $query->on('file_shares.file_id', '=', 'files.id');
            })
            ->where('files.created_by', auth()->id())
            ->when(! empty($search), function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('file_shares.created_at', 'desc')
            ->orderBy('files.id', 'desc')
            ->orderBy('is_folder', 'desc');
    }

    public static function visibleFilesBySearch(string $search)
    {
        return File::query()
            ->select('files.*')
            ->leftJoin('file_shares', 'file_shares.file_id', '=', 'files.id')
            ->whereRaw('files.name like ?', "%$search%")
            ->where(function ($query) {
                return $query
                    ->where('files.created_by', auth()->id())
                    ->orWhere('file_shares.user_id', auth()->id());
            })
            ->where('_lft', '!=', 1)
            ->orderBy('file_shares.created_at', 'desc')
            ->orderBy('files.id', 'desc')
            ->orderBy('is_folder', 'desc')
            ->get();
    }

    public function saveFileTree(array $fileTree, File $parent, int $user)
    {
        foreach ($fileTree as $name => $file) {
            if (is_array($file)) {
                $folder = new File();
                $folder->is_folder = true;
                $folder->name = $name;
                $parent->appendNode($folder);
                $this->saveFileTree($file, $folder, $user);
            } else {
                $this->saveFile($file, auth()->user(), $parent);
            }
        }
    }

    public function saveFile(UploadedFile $file, User $user, File $parent)
    {
        $model = new File();

        $path = $file->store('/files/'.$user->id, [
            'disk' => 'local',
        ]);

        $model->storage_path = $path;
        $model->is_folder = false;
        $model->name = $file->getClientOriginalName();
        $model->size = $file->getSize();
        $model->mime = $file->getMimeType();
        $model->created_by = $user->id;
        $model->parent_id = $parent->id;
        $model->uploaded_on_cloud = 0;

        $parent->appendNode($model);

        //start background job for file upload
        UploadFileToCloudJob::dispatch($model);

        return $model;
    }

    public function createZip($files): string
    {
        $zipPath = 'zip/'.\Illuminate\Support\Str::random().'.zip';
        $publicPath = "$zipPath";

        $directory = dirname($publicPath);
        if (! is_dir($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $zipFile = Storage::disk('public')->path($publicPath);

        $zip = new \ZipArchive();

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            $this->addFilesToZip($zip, $files);
        }

        $zip->close();

        return asset(Storage::disk('public')->url($publicPath));
    }

    // ''
    // '/imagens'
    // '/imagens/teste.png'
    private function addFilesToZip(\ZipArchive $zip, Collection $files, string $ancestors = '')
    {
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->addFilesToZip($zip, $file->children, $ancestors.'/'.$file->name);
            } else {
                $localPath = Storage::disk('local')->path($file->storage_path);
                if ($file->uploaded_on_cloud) {
                    $dest = pathinfo($file->storage_path, PATHINFO_BASENAME);
                    $content = Storage::get($file->storage_path);
                    Storage::disk('public')->put($dest, $content);
                    $localPath = Storage::disk('public')->path($dest);
                }

                $zip->addFile($localPath, $ancestors.'/'.$file->name);
            }
        }
    }

    public function getDownloadUrl(
        array $ids,
        string $zipName
    ) {
        if (count($ids) === 1) {
            $file = File::find($ids[0]);
            if ($file->is_folder) {
                if ($file->children->count() === 0) {
                    throw new \Exception('The folder is empty');
                }

                $url = (new File())->createZip($file->children);
                $fileName = $file->name.'.zip';
            } else {
                $dest = pathinfo($file->storage_path, PATHINFO_BASENAME);

                if ($file->uploaded_on_cloud) {
                    $content = Storage::get($file->storage_path);
                } else {
                    $content = Storage::disk('local')->get($file->storage_path);
                }

                Storage::disk('public')->put($dest, $content);

                $url = asset(Storage::disk('public')->url($dest));
                $fileName = $file->name;
            }
        } else {
            $files = File::query()->whereIn('id', $ids)->get();
            $url = (new File())->createZip($files);
            $fileName = "{$zipName}.zip";
        }

        return [$url, $fileName];
    }

    public function arrayOfSharedFilesWithUser(
        array $ids,
        int $userId
    ): array {
        return FileShare::query()
            ->whereIn('file_id', $ids)
            ->where('user_id', $userId)
            ->pluck('file_id')
            ->toArray();
    }
}
