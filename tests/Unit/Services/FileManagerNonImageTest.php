<?php

namespace LucasBarros\LaravelFileManager\Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LucasBarros\LaravelFileManager\Models\File as FileModel;
use LucasBarros\LaravelFileManager\Services\FileManagerService;
use LucasBarros\LaravelFileManager\Tests\TestCase;

class FileManagerNonImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uploads_a_non_image_file_without_optimization(): void
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
            'file-manager.pdf.optimization.enabled',
            true
        );

        $uploadedFile = UploadedFile::fake()->create(
            'documento.txt',
            100,
            'text/plain'
        );

        $file = app(FileManagerService::class)->upload(
            $uploadedFile
        );

        $this->assertInstanceOf(
            FileModel::class,
            $file
        );

        $this->assertSame(
            'txt',
            $file->extension
        );

        $this->assertSame(
            'text/plain',
            $file->mime_type
        );

        $this->assertSame(
            'documento.txt',
            $file->original_name
        );

        $this->assertGreaterThan(
            0,
            $file->size
        );

        Storage::disk('local')->assertExists(
            $file->path
        );
    }
}