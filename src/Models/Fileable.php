<?php

namespace LucasBarros\LaravelFileManager\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fileable extends MorphPivot
{
    protected $table = 'fileables';

    protected $fillable = [
        'file_id',

        'fileable_type',
        'fileable_id',

        'collection',
        'type',

        'sort_order',
    ];

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