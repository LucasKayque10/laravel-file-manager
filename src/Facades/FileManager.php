<?php

namespace LucasBarros\LaravelFileManager\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \LucasBarros\LaravelFileManager\Models\File upload(
 *     \Illuminate\Http\UploadedFile $file,
 *     ?string $disk = null,
 *     ?string $visibility = null
 * )
 */
class FileManager extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'file-manager';
    }
}