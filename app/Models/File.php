<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use App\Trait\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use \Illuminate\Support\Str;

class File extends Model
{
    use HasFactory, NodeTrait, SoftDeletes, HasCreatorAndUpdater;

    protected $fillable = ['name', 'is_folder', 'user_id', 'uploaded_on_cloud'];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->parent) {
                return;
            }

            $model->path = ( !$model->parent->isRoot() ? $model->parent->path . '/' : '' ) . Str::slug($model->name);
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

        return number_format($this->size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
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

    public function deleteFilesFromStorage(Collection|array $files)
    {
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->deleteFilesFromStorage($file->children);
            } else {
                Storage::delete($file->storage_path);
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
            ->when(!empty($search),function($query) use($search){
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
            ->when(!empty($search),function($query) use($search){
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
            ->whereRaw('files.name like ?',"%$search%")
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
}
