<?php

namespace LucasBarros\LaravelFileManager\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use LucasBarros\LaravelFileManager\Models\File as FileModel;
use LucasBarros\LaravelFileManager\Services\FileManagerService;
use LucasBarros\LaravelFileManager\Tests\TestCase;

class FileManagerServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_it_uploads_and_optimizes_an_image(): void
    {
        Storage::fake('local');

        config()->set(
            'file-manager.disk',
            'local'
        );

        config()->set(
            'file-manager.image.optimization.enabled',
            true
        );

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
            . '/file-manager-upload-test-'
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
                'foto.jpg',
                'image/jpeg',
                null,
                true
            );

            $file = app(FileManagerService::class)->upload(
                $uploadedFile
            );

            $this->assertInstanceOf(
                FileModel::class,
                $file
            );

            $this->assertSame(
                'webp',
                $file->extension
            );

            $this->assertSame(
                'image/webp',
                $file->mime_type
            );

            $this->assertGreaterThan(
                0,
                $file->size
            );

            $this->assertNotSame(
                'image/jpeg',
                $file->mime_type
            );

            Storage::disk('local')->assertExists(
                $file->path
            );

            $storedPath = Storage::disk('local')->path(
                $file->path
            );

            $this->assertSame(
                filesize($storedPath),
                $file->size
            );

            $this->assertSame(
                hash_file(
                    config('file-manager.hash_algorithm', 'sha256'),
                    $storedPath
                ),
                $file->hash
            );

            $storedImage = $manager->read(
                $storedPath
            );

            $this->assertSame(
                1600,
                $storedImage->width()
            );

            $this->assertSame(
                1067,
                $storedImage->height()
            );
        } finally {
            @unlink($sourcePath);
        }
    }

    public function test_it_uploads_image_without_optimization_when_disabled(): void
    {
        Storage::fake('local');

        config()->set('file-manager.disk', 'local');

        config()->set(
            'file-manager.image.optimization.enabled',
            false
        );

        $sourcePath = sys_get_temp_dir()
            . '/file-manager-upload-test-'
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
                'foto.jpg',
                'image/jpeg',
                null,
                true
            );

            $file = app(FileManagerService::class)->upload(
                $uploadedFile
            );

            $this->assertInstanceOf(
                FileModel::class,
                $file
            );

            $this->assertSame(
                'jpg',
                $file->extension
            );

            $this->assertSame(
                'image/jpeg',
                $file->mime_type
            );

            $this->assertGreaterThan(
                0,
                $file->size
            );

            $this->assertSame(
                'foto.jpg',
                $file->original_name
            );

            Storage::disk('local')->assertExists(
                $file->path
            );

            $storedPath = Storage::disk('local')->path(
                $file->path
            );

            $storedImage = $manager->read(
                $storedPath
            );

            $this->assertSame(
                3000,
                $storedImage->width()
            );

            $this->assertSame(
                2000,
                $storedImage->height()
            );
        } finally {
            @unlink($sourcePath);
        }
    }
}