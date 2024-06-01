<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileActionRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\TrashFilesRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FileController extends Controller
{
    public function myFiles(Request $request, string $folder = null)
    {
        if ($folder) {
            $folder = File::query()
                ->where('created_by', auth()->id())
                ->where('path', $folder)
                ->firstOrFail();
        }

        if (!$folder) {
            $folder = $this->getRoot();
        }

        $files = File::query()
            ->where('parent_id', $folder->id)
            ->where('created_by', auth()->id())
            ->orderBy('is_folder', 'desc')
            ->orderBy('created_at', 'desc')
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
        $files = File::query()
            ->onlyTrashed()
            ->where('created_by', auth()->id())
            ->orderBy('is_folder', 'desc')
            ->orderBy('deleted_at', 'desc')
            ->paginate(20);

        $files =  FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('Trash', [
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

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download'
            ];
        }


        if ($all) {
            $url = $this->createZip($parent->children);
            $fileName = $parent->name . '.zip';
        } else {
            if (count($ids) === 1) {
                $file = File::find($ids[0]);
                if ($file->is_folder) {
                    if ($file->children->count() === 0) {
                        return [
                            'message' => 'The folder is empty'
                        ];
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
                $fileName = $parent->name . '.zip';
            }
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
}
