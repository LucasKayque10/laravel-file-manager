<?php

namespace LucasBarros\LaravelFileManager\Services;

use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LucasBarros\LaravelFileManager\Models\File as FileModel;

class FileManagerService
{
    public function __construct(
        private ImageOptimizer $imageOptimizer,
    ) {}

    public function upload(
        UploadedFile $fileTemp,
        ?string $disk = null,
        ?string $visibility = null,
    ): FileModel {
        return DB::transaction(function () use (
            $fileTemp,
            $disk,
            $visibility
        ) {
            $disk = $disk
                ?? config('file-manager.disk', 'local');

            $visibility = $visibility
                ?? config('file-manager.visibility', 'private');

            $uuid = (string) Str::uuid();

            $isImage = str_starts_with(
                $fileTemp->getMimeType() ?? '',
                'image/'
            );

            $optimizedFile = null;

            try {
                if (
                    $isImage
                    && config(
                        'file-manager.image.optimization.enabled',
                        true
                    )
                ) {
                    $optimizedFile = $this->imageOptimizer->optimize(
                        $fileTemp
                    );

                    $directory = $this->getDirectory();

                    $filename = $uuid . '.'
                        . $optimizedFile['extension'];

                    $path = Storage::disk($disk)->putFileAs(
                        $directory,
                        new HttpFile($optimizedFile['path']),
                        $filename,
                        [
                            'visibility' => $visibility,
                        ]
                    );

                    if (! $path) {
                        throw new \RuntimeException(
                            'Failed to store optimized image.'
                        );
                    }

                    $extension = $optimizedFile['extension'];
                    $mimeType = $optimizedFile['mime_type'];
                    $size = $optimizedFile['size'];

                    $hash = hash_file(
                        config(
                            'file-manager.hash_algorithm',
                            'sha256'
                        ),
                        $optimizedFile['path']
                    );
                } else {
                    $hash = hash_file(
                        config(
                            'file-manager.hash_algorithm',
                            'sha256'
                        ),
                        $fileTemp->getRealPath()
                    );

                    $directory = $this->getDirectory();

                    $filename = $uuid . '.'
                        . $fileTemp->extension();

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

                    $extension = $fileTemp
                        ->getClientOriginalExtension();

                    $mimeType = $fileTemp->getMimeType();

                    $size = $fileTemp->getSize();
                }

                return FileModel::create([
                    'uuid' => $uuid,

                    'disk' => $disk,
                    'path' => $path,
                    'visibility' => $visibility,

                    'original_name' => $fileTemp
                        ->getClientOriginalName(),

                    'extension' => $extension,

                    'mime_type' => $mimeType,

                    'size' => $size,

                    'hash' => $hash,

                    'creator_type' => auth()->user()?->getMorphClass(),
                    'creator_id' => auth()->id(),
                ]);
            } finally {
                if (
                    $optimizedFile !== null
                    && isset($optimizedFile['path'])
                    && is_file($optimizedFile['path'])
                ) {
                    @unlink($optimizedFile['path']);
                }
            }
        });
    }

    private function getDirectory(): string
    {
        $directory = config(
            'file-manager.path_prefix',
            'files'
        );

        if (
            config(
                'file-manager.use_date_directories',
                true
            )
        ) {
            $directory .= '/' . date('Y/m');
        }

        return $directory;
    }
}
