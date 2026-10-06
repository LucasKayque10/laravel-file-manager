<?php

namespace LucasBarros\LaravelFileManager\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class ImageOptimizer
{
    /**
     * Otimiza uma imagem e retorna o caminho do arquivo temporário.
     *
     * @return array{
     *     path: string,
     *     extension: string,
     *     mime_type: string,
     *     size: int
     * }
     */
    public function optimize(UploadedFile $file): array
    {
        $driver = match (
            strtolower(
                config('file-manager.image.optimization.driver', 'imagick')
            )
        ) {
            'gd' => GdDriver::class,
            'imagick' => ImagickDriver::class,

            default => throw new \InvalidArgumentException(
                'Unsupported image optimization driver.'
            ),
        };

        $manager = new ImageManager($driver);

        $image = $manager->read(
            $file->getRealPath()
        );

        $image->scaleDown(
            width: (int) config(
                'file-manager.image.optimization.max_width',
                1600
            ),
            height: (int) config(
                'file-manager.image.optimization.max_height',
                1600
            )
        );

        $format = strtolower(
            config(
                'file-manager.image.optimization.format',
                'webp'
            )
        );

        $quality = (int) config(
            'file-manager.image.optimization.quality',
            90
        );

        $encoded = $image->encodeByExtension(
            $format,
            quality: $quality
        );

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'file-manager-'
        );

        if ($temporaryPath === false) {
            throw new \RuntimeException(
                'Failed to create temporary file for image optimization.'
            );
        }

        try {
            $bytes = file_put_contents(
                $temporaryPath,
                (string) $encoded
            );

            if ($bytes === false) {
                throw new \RuntimeException(
                    'Failed to write optimized image.'
                );
            }

            return [
                'path' => $temporaryPath,
                'extension' => $format,
                'mime_type' => mime_content_type($temporaryPath)
                    ?: 'image/' . $format,
                'size' => filesize($temporaryPath) ?: 0,
            ];
        } catch (\Throwable $exception) {
            @unlink($temporaryPath);

            throw $exception;
        }
    }
}