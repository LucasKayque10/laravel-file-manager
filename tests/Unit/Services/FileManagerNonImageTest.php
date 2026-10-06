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

        config()->set('file-manager.disk', 'local');
        config()->set(
            'file-manager.image.optimization.enabled',
            true
        );

        $uploadedFile = UploadedFile::fake()->create(
            'documento.pdf',
            100,
            'application/pdf'
        );

        $file = app(FileManagerService::class)->upload(
            $uploadedFile
        );

        $this->assertInstanceOf(FileModel::class, $file);

        $this->assertSame(
            'pdf',
            $file->extension
        );

        $this->assertSame(
            'application/pdf',
            $file->mime_type
        );

        $this->assertSame(
            'documento.pdf',
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