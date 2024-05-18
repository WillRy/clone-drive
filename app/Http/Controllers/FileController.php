<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

class FileController extends Controller
{
    public function myFiles(string $folder = null)
    {
        if($folder) {
            $folder = File::query()
                ->where('created_by', auth()->id())
                ->where('path', $folder)
                ->firstOrFail();
        }

        if(!$folder) {
            $folder = $this->getRoot();
        }

        $files = File::query()
            ->where('parent_id', $folder->id)
            ->where('created_by', auth()->id())
            ->orderBy('is_folder', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);


        $ancestors = FileResource::collection([...$folder->ancestors,$folder]);

        $folder = new FileResource($folder);

        return Inertia::render('MyFiles', [
            'files' => FileResource::collection($files),
            'folder' => $folder,
            'ancestors' => $ancestors,
        ]);
    }

    public function createFolder(StoreFolderRequest $request)
    {
        $data = $request->validated();

        $parent = $request->parent;

        if(!$parent) {
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

        if(!$parent) {
            $parent = $this->getRoot();
        }

        if(!empty($fileTree)) {
            $this->saveFileTree($fileTree, $parent, $user);
        } else {
            /** @var UploadedFile $file */
            foreach($data['files'] as $file) {
                $this->saveFile($file, auth()->user(), $parent);
            }
        }

    }

    private function getRoot(): File
    {
        return File::query()->where('created_by', auth()->id())->whereIsRoot()->first();
    }

    private function saveFileTree(array $fileTree, File $parent, int $user)
    {
        foreach($fileTree as $name => $file) {
            if(is_array($file)) {
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

        $path = $file->store('/files/'.$user->id);

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
}
