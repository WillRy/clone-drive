<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Http\UploadedFile;

class StoreFileRequest extends ParentIdBaseRequest
{

    public function detectFolderName(array $paths = [])
    {
        if(!$paths) {
            return null;
        }


        $parts = explode('/', $paths[0] ?? '');

        return $parts[0] ?? null;
    }

    public function buildFileTree(array $paths, array $files)
    {
        $filePaths = array_slice($paths, 0, count($paths));
        $filePaths =  array_filter($filePaths, fn($f) => !empty($f));

        $tree = [];

        foreach ($filePaths as $ind => $filePath) {
            $parts = explode('/', $filePath);
            $currentNode = &$tree;
            foreach($parts as $i => $part) {
                if(!isset($currentNode[$part])) {
                    $currentNode[$part] = [];
                }

                if($i === count($parts) - 1) {
                    $currentNode[$part] = $files[$ind];
                } else {
                    $currentNode = &$currentNode[$part];
                }
            }
        }

        return $tree;
    }

    protected function prepareForValidation()
    {
        $paths = array_filter($this->relative_paths ?? [], fn($f) => !empty($f));
        $this->merge([
            'file_paths' => $paths,
            'folder_name' => $this->detectFolderName($paths)
        ]);
    }

    protected function passedValidation()
    {
        $data = $this->validated();


        $this->replace([
            'file_tree' => $this->buildFileTree($this->file_paths, $data['files'])
        ]);
    }


    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'files.*' => [
                'required',
                'file',
                function($attribute, $value, $fail) {
                    /** @var UploadedFile $value */

                    //ignore if is a folder upload
                    if($this->folder_name) {
                        return;
                    }

                    $exists = File::query()
                        ->where('name', $value->getClientOriginalName())
                        ->where('created_by', auth()->id())
                        ->where('parent_id', $this->parent_id)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
                        $fail("File {$value->getClientOriginalName()} already exists in this folder");
                    }
                }
            ],
            'folder_name' => [
                'nullable',
                'string',
                function($attribute, $value, $fail) {


                    //ignore if is a file upload
                    if(!$value) {
                        return;
                    }

                    $exists = File::query()
                        ->where('name', $value)
                        ->where('created_by', auth()->id())
                        ->where('parent_id', $this->parent_id)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
                        $fail("Folder {$value} already exists in this folder");
                    }
                }
            ]
        ]);
    }
}
