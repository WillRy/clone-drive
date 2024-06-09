<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToFavouritesRequest;
use App\Http\Requests\FileActionRequest;
use App\Http\Requests\ShareFilesRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\TrashFilesRequest;
use App\Http\Resources\FileResource;
use App\Mail\ShareFilesMail;
use App\Models\File;
use App\Models\FileShare;
use App\Models\StarredFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FileController extends Controller
{
    public function myFiles(Request $request, string $folder = null)
    {
        $search = $request->input('search');

        if ($folder) {
            $folder = File::query()
                ->where('created_by', auth()->id())
                ->where('path', $folder)
                ->firstOrFail();
        }

        if (!$folder) {
            $folder = $this->getRoot();
        }

        $favourites = (int) $request->get('favourites');

        $files = File::query()
            ->with('starred')
            ->where('created_by', auth()->id())
            ->where('_lft', '!=', 1)
            ->when($favourites, function ($query) {
                //this relation already filters by the authenticated user
                $query->whereHas('starred');
            })
            ->where(function($query) use($folder, $search){
                if(empty($search)) {
                    $query->where('parent_id', $folder->id);
                } else {
                    $query->where('name', 'like', "%{$search}%");
                }
            })
            ->orderBy('is_folder', 'desc')
            ->orderBy('files.created_at', 'desc')
            ->orderBy('files.id', 'desc')
            ->paginate(20);


        $ancestors = FileResource::collection([...$folder->ancestors, $folder]);

        $folder = new FileResource($folder);

        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('MyFiles', [
            'files' => $files,
            'folder' => $folder,
            'ancestors' => $ancestors,
        ]);
    }

    public function trash(Request $request)
    {
        $search = $request->input('search');
        $files = File::query()
            ->onlyTrashed()
            ->where('created_by', auth()->id())
            ->when(!empty($search),function($query) use($search){
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('is_folder', 'desc')
            ->orderBy('deleted_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        $files =  FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('Trash', [
            'files' => $files
        ]);
    }


    public function sharedWithMe(Request $request)
    {
        $files = File::getSharedWithMe($request->input('search'))->paginate(20);

        $files =  FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('SharedWithMe', [
            'files' => $files
        ]);
    }

    public function sharedByMe(Request $request)
    {
        $files = File::getSharedByMe($request->input('search'))->paginate(20);

        $files =  FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('SharedByMe', [
            'files' => $files
        ]);
    }

    public function createFolder(StoreFolderRequest $request)
    {
        $data = $request->validated();

        $parent = $request->parent;

        if (!$parent) {
            $parent = $this->getRoot();
        }

        $file = new File();
        $file->is_folder = true;
        $file->name = $data['name'];

        $parent->appendNode($file);
    }

    public function store(StoreFileRequest $request)
    {
        $data = $request->all();
        $parent = $request->parent;
        $fileTree = $request->file_tree;
        $user = auth()->user()->id;

        if (!$parent) {
            $parent = $this->getRoot();
        }

        if (!empty($fileTree)) {
            $this->saveFileTree($fileTree, $parent, $user);
        } else {
            /** @var UploadedFile $file */
            foreach ($data['files'] as $file) {
                $this->saveFile($file, auth()->user(), $parent);
            }
        }
    }

    public function destroy(FileActionRequest $request)
    {
        $data = $request->validated();

        $parent = $request->parent;

        if ($data['all']) {
            $children = $parent->children;
            foreach ($children as $child) {
                $child->moveToTrash();
            }
        } else {
            foreach ($data['ids'] ?? [] as $id) {
                $file = File::find($id);
                if ($file) {
                    $file->moveToTrash();
                }
            }
        }

        return to_route('myFiles', ['folder' => $parent->path]);
    }

    public function download(FileActionRequest $request)
    {
        $data = $request->validated();

        $parent = $request->parent;

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];
        $zipName = $parent->name;

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }


        if ($all) {
            $url = $this->createZip($parent->children);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = $this->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName
        ];
    }


    private function getRoot(): File
    {
        return File::query()->where('created_by', auth()->id())->whereIsRoot()->first();
    }

    private function saveFileTree(array $fileTree, File $parent, int $user)
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


    private function saveFile(UploadedFile $file, User $user, File $parent)
    {
        $model = new File();

        $path = $file->store('/files/' . $user->id);

        $model->storage_path = $path;
        $model->is_folder = false;
        $model->name = $file->getClientOriginalName();
        $model->size = $file->getSize();
        $model->mime = $file->getMimeType();
        $model->created_by = $user->id;
        $model->parent_id = $parent->id;

        $parent->appendNode($model);

        return $model;
    }

    private function createZip($files): string
    {
        $zipPath = 'zip/' . \Illuminate\Support\Str::random() . '.zip';
        $publicPath = "public/$zipPath";

        $directory = dirname($publicPath);
        if (!is_dir($directory)) {
            Storage::makeDirectory($directory);
        }


        $zipFile = Storage::path($publicPath);

        $zip = new \ZipArchive();

        if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            $this->addFilesToZip($zip, $files);
        }

        $zip->close();

        return asset(Storage::url($publicPath));
    }

    // ''
    // '/imagens'
    // '/imagens/teste.png'
    private function addFilesToZip(\ZipArchive $zip, Collection $files, string $ancestors = '')
    {
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->addFilesToZip($zip, $file->children, $ancestors . '/' . $file->name);
            } else {
                $zip->addFile(Storage::path($file->storage_path), $ancestors . '/' . $file->name);
            }
        }
    }

    public function restore(TrashFilesRequest $request)
    {
        $data = $request->validated();

        if ($data['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->restore();
            }
        } else {
            $ids = $data['ids'] ?? [];
            $files = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($files as $file) {
                $file->restore();
            }
        }

        return to_route('trash');
    }

    public function deleteForever(TrashFilesRequest $request)
    {
        $data = $request->validated();

        if ($data['all']) {
            $children = File::onlyTrashed()->get();
            foreach ($children as $child) {
                $child->deleteForever();
            }
        } else {
            $ids = $data['ids'] ?? [];
            $files = File::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($files as $file) {
                $file->deleteForever();
            }
        }

        return to_route('trash');
    }

    public function addToFavourites(AddToFavouritesRequest $request)
    {
        $data = $request->validated();

        $file = StarredFile::query()
            ->where('file_id', $data['id'])
            ->where('user_id', auth()->id())
            ->first();

        if ($file) {
            $file->delete();
        } else {
            StarredFile::create([
                'file_id' => $data['id'],
                'user_id' => auth()->id()
            ]);
        }

        return response()->noContent();
    }

    public function share(ShareFilesRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];
        $email = $data['email'] ?? [];
        $parent = $request->parent;

        //flash message
        if (!$all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to share');
        }

        $user = User::query()->where('email', $email)->first();

        //não avisar que o email não existe, por razão de segurança
        if (!$user) {
            return redirect()->back();
        }

        if ($all) {
            $files = $parent->children;
        } else {
            $files = File::query()->whereIn('id', $ids)->get();
        }

        $existingFiles = FileShare::query()
            ->whereIn('file_id', $files->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('file_id')
            ->toArray();

        $data = [];
        foreach ($files as $file) {
            if (in_array($file->id, $existingFiles)) {
                continue;
            }

            $data[] = [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        FileShare::insert($data);


        Mail::to($user->email)->send(new ShareFilesMail(
            $user,
            auth()->user(),
            $files
        ));

        return redirect()->back();
    }


    public function downloadSharedWithMe(FileActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];
        $zipName = 'share_with_me';

        if (!$all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to download');
        }

        if ($all) {
            $files = File::getSharedWithMe()->get();

            $url = $this->createZip($files);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = $this->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName
        ];
    }

    public function downloadSharedByMe(FileActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];
        $zipName = 'share_by_me';

        if (!$all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to download');
        }

        if ($all) {
            $files = File::getSharedByMe()->get();

            $url = $this->createZip($files);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = $this->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName
        ];
    }

    public function downloadSearch(FileActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];
        $zipName = 'search';

        if (!$all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to download');
        }

        if ($all) {
            $files = File::visibleFilesBySearch($request->search);

            $url = $this->createZip($files);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = $this->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName
        ];
    }


    private function getDownloadUrl(
        array $ids,
        string $zipName
    ) {
        if (count($ids) === 1) {
            $file = File::find($ids[0]);
            if ($file->is_folder) {
                if ($file->children->count() === 0) {
                    throw new \Exception('The folder is empty');
                }

                $url = $this->createZip($file->children);
                $fileName = $file->name . '.zip';
            } else {
                $dest = 'public/' . pathinfo($file->storage_path, PATHINFO_BASENAME);
                Storage::copy($file->storage_path, $dest);

                $url = asset(Storage::url($dest));
                $fileName = $file->name;
            }
        } else {
            $files = File::query()->whereIn('id', $ids)->get();
            $url = $this->createZip($files);
            $fileName = "{$zipName}.zip";
        }

        return  [$url, $fileName];
    }
}
