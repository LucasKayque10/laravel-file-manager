<?php

namespace LucasBarros\LaravelFileManager\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @property string $uuid
 * @property string|null $description
 * @property string $collection
 * @property string|null $type
 * @property int $sort_order
 */
class Fileable extends MorphPivot
{
    public const PIVOT_COLUMNS = [
        'uuid',
        'description',
        'collection',
        'type',
        'sort_order',
    ];

    protected $table = 'fileables';

    /*
     * Fileable também é usado como model comum (via morphMany)
     * pelo HasFiles. O Pivot desativa estes dois comportamentos
     * por padrão, então são reativados aqui.
     */
    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'uuid',

        'file_id',

        'fileable_type',
        'fileable_id',

        'description',

        'collection',
        'type',

        'sort_order',
    ];

    protected static function booted(): void
    {
        static::creating(function (Fileable $fileable) {
            $fileable->uuid ??= (string) Str::uuid();
        });

        static::deleted(function (Fileable $fileable) {
            $fileId = $fileable->file_id;

            if (! $fileId) {
                return;
            }

            if (! config('file-manager.auto_delete_orphan_file')){
                return;
            }

            DB::afterCommit(function () use ($fileId) {
                $file = File::query()->find($fileId);

                if ($file && $file->isOrphan()) {
                    $file->forceDelete();
                }
            });
        });
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(
            File::class
        );
    }

    public function fileable()
    {
        return $this->morphTo();
    }
}