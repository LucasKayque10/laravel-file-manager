<?php

namespace LucasBarros\LaravelFileManager\Tests\Unit\Models;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LucasBarros\LaravelFileManager\Models\File;
use LucasBarros\LaravelFileManager\Models\Fileable;
use LucasBarros\LaravelFileManager\Models\FileShare;
use LucasBarros\LaravelFileManager\Tests\Support\HasFilesTestCase;
use LucasBarros\LaravelFileManager\Tests\Support\HasFilesTestModel;

class FileModelsTest extends HasFilesTestCase
{
    public function test_file_exists_returns_true_when_physical_file_exists(): void
    {
        Storage::fake('local');

        $file = $this->createFile();
        Storage::disk('local')->put($file->path, 'conteudo');

        $this->assertTrue($file->fileExists());
    }

    public function test_file_exists_returns_false_when_physical_file_does_not_exist(): void
    {
        Storage::fake('local');

        $file = $this->createFile();

        $this->assertFalse($file->fileExists());
    }

    public function test_file_url_returns_storage_url(): void
    {
        Storage::fake('local');

        $file = $this->createFile();

        $this->assertSame(
            Storage::disk($file->disk)->url($file->path),
            $file->url(),
        );
    }

    public function test_download_url_returns_file_url(): void
    {
        Storage::fake('local');

        $file = $this->createFile();

        $this->assertSame($file->url(), $file->downloadUrl());
    }

    public function test_fileables_relation_returns_attached_fileables(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, 'documents');

        $this->assertCount(1, $file->fileables);
        $this->assertInstanceOf(Fileable::class, $file->fileables->first());
    }

    public function test_shares_relation_returns_file_shares(): void
    {
        $file = $this->createFile();

        $share = FileShare::query()->create([
            'file_id' => $file->id,
            'token' => (string) Str::uuid(),
        ]);

        $this->assertCount(1, $file->shares);
        $this->assertTrue($file->shares->contains('id', $share->id));
    }

    public function test_creator_relation_resolves_morph_to_owner(): void
    {
        $owner = $this->createOwner();

        $file = $this->createFile();

        $file->update([
            'creator_type' => $owner->getMorphClass(),
            'creator_id' => $owner->id,
        ]);

        $this->assertInstanceOf(HasFilesTestModel::class, $file->creator);
        $this->assertSame($owner->id, $file->creator->id);
    }

    public function test_is_orphan_returns_true_without_fileables(): void
    {
        $file = $this->createFile();

        $this->assertTrue($file->isOrphan());
    }

    public function test_is_orphan_returns_false_with_fileables(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, 'documents');

        $this->assertFalse($file->isOrphan());
    }

    public function test_delete_file_deletes_physical_file_and_soft_deletes_record(): void
    {
        Storage::fake('local');

        $file = $this->createFile();
        Storage::disk('local')->put($file->path, 'conteudo');

        $this->assertTrue($file->deleteFile());

        Storage::disk('local')->assertMissing($file->path);

        $this->assertSoftDeleted('files', [
            'id' => $file->id,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
        ]);
    }

    public function test_delete_file_soft_deletes_record_when_physical_file_is_missing(): void
    {
        Storage::fake('local');

        $file = $this->createFile();

        $this->assertTrue($file->deleteFile());

        $this->assertSoftDeleted('files', [
            'id' => $file->id,
        ]);
    }

    public function test_force_deleting_file_deletes_physical_file_when_enabled(): void
    {
        Storage::fake('local');

        config()->set('file-manager.auto_delete_physical_file', true);

        $file = $this->createFile();

        Storage::disk('local')->put($file->path, 'conteudo');

        $path = $file->path;

        $file->forceDelete();

        Storage::disk('local')->assertMissing($path);

        $this->assertDatabaseMissing('files', [
            'id' => $file->id,
        ]);
    }

    public function test_force_deleting_file_preserves_physical_file_when_disabled(): void
    {
        Storage::fake('local');

        config()->set('file-manager.auto_delete_physical_file', false);

        $file = $this->createFile();

        Storage::disk('local')->put($file->path, 'conteudo');

        $path = $file->path;

        $file->forceDelete();

        Storage::disk('local')->assertExists($path);
    }

    public function test_fileable_generates_uuid_when_creating_without_one(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $fileable = Fileable::query()->create([
            'file_id' => $file->id,
            'fileable_type' => $owner->getMorphClass(),
            'fileable_id' => $owner->id,
            'collection' => 'documents',
        ]);

        $this->assertNotEmpty($fileable->uuid);
        $this->assertTrue(Str::isUuid($fileable->uuid));
    }

    public function test_fileable_preserves_provided_uuid(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();
        $uuid = (string) Str::uuid();

        $fileable = Fileable::query()->create([
            'uuid' => $uuid,
            'file_id' => $file->id,
            'fileable_type' => $owner->getMorphClass(),
            'fileable_id' => $owner->id,
            'collection' => 'documents',
        ]);

        $this->assertSame($uuid, $fileable->uuid);
    }

    public function test_fileable_file_relation_resolves_file(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $fileable = Fileable::query()->create([
            'file_id' => $file->id,
            'fileable_type' => $owner->getMorphClass(),
            'fileable_id' => $owner->id,
            'collection' => 'documents',
        ]);

        $this->assertTrue($fileable->file->is($file));
    }

    public function test_fileable_fileable_relation_resolves_owner(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $fileable = Fileable::query()->create([
            'file_id' => $file->id,
            'fileable_type' => $owner->getMorphClass(),
            'fileable_id' => $owner->id,
            'collection' => 'documents',
        ]);

        $this->assertTrue($fileable->fileable->is($owner));
    }

    public function test_deleting_fileable_preserves_file_when_auto_delete_is_disabled(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', false);

        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, 'documents');

        $fileable = $file->fileables()->firstOrFail();
        $fileable->delete();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
        ]);
    }

    public function test_deleting_fileable_preserves_shared_file_when_auto_delete_is_enabled(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', true);

        $owner1 = $this->createOwner('Owner 1');
        $owner2 = $this->createOwner('Owner 2');
        $file = $this->createFile();

        $owner1->attachFile($file, 'documents');
        $owner2->attachFile($file, 'documents');

        $fileable = $file->fileables()
            ->where('fileable_id', $owner1->id)
            ->firstOrFail();

        $fileable->delete();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
        ]);
    }
}