<?php

namespace LucasBarros\LaravelFileManager\Services;

use LucasBarros\LaravelFileManager\Models\File as FileModel;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class FileManagerService
{
    public function upload(
        UploadedFile $fileTemp,
        ?string $disk = null,
        ?string $visibility = null,
    ): FileModel
    {
        return DB::transaction(function () use (
            $fileTemp,
            $disk,
            $visibility
        ) {
            $disk = $disk
                ?? config('file-manager.disk', 'local');

            $visibility = $visibility
                ?? config('file-manager.visibility', 'private');
            
            $hash = hash_file(
                config('file-manager.hash_algorithm', 'sha256'),
                $fileTemp->getRealPath()
            );

            $directory = config('file-manager.path_prefix', 'files');

            if (config('file-manager.use_date_directories', true)) {
                $directory .= '/' . date('Y/m');
            }

            $uuid = (string) Str::uuid();
            $filename = $uuid . '.' . $fileTemp->extension();

            $path = $fileTemp->storeAs(
                $directory,
                $filename,
                [
                    'disk' => $disk,
                    'visibility' => $visibility,
                ]
            );

            if (! $path) {
                throw new \RuntimeException(
                    'Failed to store uploaded file.'
                );
            }

            return FileModel::create([
                'uuid' => $uuid,

                'disk' => $disk,
                'path' => $path,
                'visibility' => $visibility,

                'original_name' => $fileTemp->getClientOriginalName(),

                'extension' => $fileTemp->getClientOriginalExtension(),

                'mime_type' => $fileTemp->getMimeType(),

                'size' => $fileTemp->getSize(),

                'hash' => $hash,

                'creator_type' => auth()->user()?->getMorphClass(),
                'creator_id' => auth()->id(),
            ]);
        });
    }
}