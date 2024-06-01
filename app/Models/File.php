<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use App\Trait\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory, NodeTrait, SoftDeletes, HasCreatorAndUpdater;

    protected $fillable = ['name', 'is_folder', 'user_id'];


    protected static function boot()
    {
        parent::boot();

        static::creating(function($model) {
            if(!$model->parent) {
                return;
            }

            $model->path = (!$model->parent->isRoot() ? $model->parent->path . '/' : '') . \Illuminate\Support\Str::slug($model->name);
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

    public function owner(): Attribute
    {
        return Attribute::make(
            get: function(mixed $value, array $attributes)  {
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
            if($file->is_folder) {
                $this->deleteFilesFromStorage($file->children);
            } else {
                Storage::delete($file->storage_path);
            }
        }
    }
}
