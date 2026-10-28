<?php

namespace LucasBarros\LaravelFileManager\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LucasBarros\LaravelFileManager\Models\File;
use LucasBarros\LaravelFileManager\Tests\TestCase;

abstract class HasFilesTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('file_manager_test_owners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        config()->set('file-manager.auto_delete_orphan_file', false);
    }

    protected function createOwner(string $name = 'Test owner'): HasFilesTestModel
    {
        return HasFilesTestModel::query()->create([
            'name' => $name,
        ]);
    }

    protected function createFile(string $name = 'document.pdf'): File
    {
        return File::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'local',
            'path' => 'files/' . Str::uuid() . '/' . $name,
            'visibility' => 'private',
            'original_name' => $name,
            'extension' => pathinfo($name, PATHINFO_EXTENSION) ?: null,
            'mime_type' => 'application/octet-stream',
            'size' => 100,
        ]);
    }
}