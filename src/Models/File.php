<?php

namespace LucasBarros\LaravelFileManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'uuid',

        'disk',
        'path',
        'visibility',

        'original_name',

        'extension',
        'mime_type',

        'size',

        'description',

        'hash',

        'creator_type',
        'creator_id',

        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $hidden = [
        'creator_type',
        'creator_id',
    ];

    protected static function booted(): void
    {
        static::forceDeleted(function (File $file) {

            if (! config('file-manager.auto_delete_physical_file')) {
                return;
            }

            Storage::disk($file->disk)->delete($file->path);
        });
    }

    public function fileables(): HasMany
    {
        return $this->hasMany(
            Fileable::class
        );
    }

    public function shares(): HasMany
    {
        return $this->hasMany(
            FileShare::class
        );
    }

    public function creator()
    {
        return $this->morphTo();
    }

    public function isOrphan(): bool
    {
        return !$this->fileables()->exists();
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)
            ->exists($this->path);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)
            ->url($this->path);
    }

    public function downloadUrl(): string
    {
        return $this->url();
    }

    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            Storage::disk($this->disk)
                ->delete($this->path);
        }

        return $this->delete();
    }
}