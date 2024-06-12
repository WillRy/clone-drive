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
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class FileController extends Controller
{
    public function myFiles(Request $request, ?string $folder = null)
    {
        $search = $request->input('search');

        if ($folder) {
            $folder = (new File())->findFolderByPathOrFail($folder);
        }

        if (! $folder) {
            $folder = File::getRoot();
        }

        $favourites = (int) $request->get('favourites');

        $files = (new File())->getMyFiles(
            $folder,
            $search,
            $favourites
        );

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
        $files = (new File())->getTrash($search);

        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('Trash', [
            'files' => $files,
        ]);
    }

    public function sharedWithMe(Request $request)
    {
        $files = File::getSharedWithMe($request->input('search'))->paginate(20);

        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('SharedWithMe', [
            'files' => $files,
        ]);
    }

    public function sharedByMe(Request $request)
    {
        $files = File::getSharedByMe($request->input('search'))->paginate(20);

        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('SharedByMe', [
            'files' => $files,
        ]);
    }

    public function createFolder(StoreFolderRequest $request)
    {
        $data = $request->validated();

        $parent = $request->parent;

        if (! $parent) {
            $parent = File::getRoot();
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

        if (! $parent) {
            $parent = File::getRoot();
        }

        if (! empty($fileTree)) {
            (new File())->saveFileTree($fileTree, $parent, $user);
        } else {
            /** @var UploadedFile $file */
            foreach ($data['files'] as $file) {
                (new File())->saveFile($file, auth()->user(), $parent);
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

        if (! $all && empty($ids)) {
            return [
                'message' => 'Please select files to download',
            ];
        }

        if ($all) {
            $url = (new File())->createZip($parent->children);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = (new File())->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName,
        ];
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
                'user_id' => auth()->id(),
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
        if (! $all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to share');
        }

        $user = User::query()->where('email', $email)->first();

        //não avisar que o email não existe, por razão de segurança
        if (! $user) {
            return redirect()->back();
        }

        if ($all) {
            $files = $parent->children;
        } else {
            $files = File::query()->whereIn('id', $ids)->get();
        }

        $existingFiles = (new File())->arrayOfSharedFilesWithUser(
            $files->pluck('id')->toArray(),
            $user->id
        );

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

        if (! $all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to download');
        }

        if ($all) {
            $files = File::getSharedWithMe()->get();

            $url = (new File())->createZip($files);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = (new File())->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName,
        ];
    }

    public function downloadSharedByMe(FileActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];
        $zipName = 'share_by_me';

        if (! $all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to download');
        }

        if ($all) {
            $files = File::getSharedByMe()->get();

            $url = (new File())->createZip($files);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = (new File())->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName,
        ];
    }

    public function downloadSearch(FileActionRequest $request)
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];
        $zipName = 'search';

        if (! $all && empty($ids)) {
            return redirect()->back()->with('error', 'Please select files to download');
        }

        if ($all) {
            $files = File::visibleFilesBySearch($request->search);

            $url = (new File())->createZip($files);
            $fileName = "{$zipName}.zip";
        } else {
            [$url, $fileName] = (new File())->getDownloadUrl($ids, $zipName);
        }

        return [
            'url' => $url,
            'fileName' => $fileName,
        ];
    }
}
