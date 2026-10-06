<?php

namespace LucasBarros\LaravelFileManager\Tests\Unit\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use LucasBarros\LaravelFileManager\Services\ImageOptimizer;
use LucasBarros\LaravelFileManager\Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    public function test_it_optimizes_image_to_webp(): void
    {
        config()->set(
            'file-manager.image.optimization.driver',
            'imagick'
        );

        config()->set(
            'file-manager.image.optimization.format',
            'webp'
        );

        config()->set(
            'file-manager.image.optimization.quality',
            90
        );

        config()->set(
            'file-manager.image.optimization.max_width',
            1600
        );

        config()->set(
            'file-manager.image.optimization.max_height',
            1600
        );

        $sourcePath = sys_get_temp_dir()
            . '/file-manager-test-'
            . uniqid()
            . '.jpg';

        try {
            $manager = new ImageManager(
                new Driver()
            );

            $image = $manager->create(
                3000,
                2000
            );

            $image->toJpeg()->save($sourcePath);

            $uploadedFile = new UploadedFile(
                $sourcePath,
                'test.jpg',
                'image/jpeg',
                null,
                true
            );

            $result = app(ImageOptimizer::class)->optimize(
                $uploadedFile
            );

            $this->assertFileExists($result['path']);

            $this->assertSame(
                'webp',
                $result['extension']
            );

            $this->assertSame(
                'image/webp',
                $result['mime_type']
            );

            $this->assertGreaterThan(
                0,
                $result['size']
            );

            $optimizedImage = $manager->read(
                $result['path']
            );

            $this->assertSame(
                1600,
                $optimizedImage->width()
            );

            $this->assertSame(
                1067,
                $optimizedImage->height()
            );
        } finally {
            @unlink($sourcePath);

            if (
                isset($result['path'])
                && is_file($result['path'])
            ) {
                @unlink($result['path']);
            }
        }
    }

    public function test_it_does_not_upscale_image_that_is_already_within_limits(): void
    {
        config()->set(
            'file-manager.image.optimization.driver',
            'imagick'
        );

        config()->set(
            'file-manager.image.optimization.format',
            'webp'
        );

        config()->set(
            'file-manager.image.optimization.quality',
            90
        );

        config()->set(
            'file-manager.image.optimization.max_width',
            1600
        );

        config()->set(
            'file-manager.image.optimization.max_height',
            1600
        );

        $sourcePath = sys_get_temp_dir()
            . '/file-manager-test-'
            . uniqid()
            . '.jpg';

        try {
            $manager = new ImageManager(
                new Driver()
            );

            $image = $manager->create(
                800,
                600
            );

            $image->toJpeg()->save($sourcePath);

            $uploadedFile = new UploadedFile(
                $sourcePath,
                'test.jpg',
                'image/jpeg',
                null,
                true
            );

            $result = app(ImageOptimizer::class)->optimize(
                $uploadedFile
            );

            $this->assertFileExists($result['path']);

            $this->assertSame(
                'webp',
                $result['extension']
            );

            $this->assertSame(
                'image/webp',
                $result['mime_type']
            );

            $optimizedImage = $manager->read(
                $result['path']
            );

            $this->assertSame(
                800,
                $optimizedImage->width()
            );

            $this->assertSame(
                600,
                $optimizedImage->height()
            );
        } finally {
            @unlink($sourcePath);

            if (
                isset($result['path'])
                && is_file($result['path'])
            ) {
                @unlink($result['path']);
            }
        }
    }
}