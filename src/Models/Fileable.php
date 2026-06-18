<?php

namespace LucasBarros\LaravelFileManager\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $description
 * @property string $collection
 * @property string|null $type
 * @property int $sort_order
 */
class Fileable extends MorphPivot
{
    public const PIVOT_COLUMNS = [
        'description',
        'collection',
        'type',
        'sort_order',
    ];
    
    protected $table = 'fileables';

    protected $fillable = [
        'file_id',

        'fileable_type',
        'fileable_id',

        'description',
        
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