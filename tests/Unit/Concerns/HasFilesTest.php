<?php

namespace LucasBarros\LaravelFileManager\Tests\Unit\Concerns;

use LucasBarros\LaravelFileManager\Models\File;
use LucasBarros\LaravelFileManager\Relations\MorphToOneFile;
use LucasBarros\LaravelFileManager\Tests\Support\HasFilesTestCase;

class HasFilesTest extends HasFilesTestCase
{
    public function test_it_attaches_a_file_and_generates_a_pivot_uuid(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile(
            file: $file,
            description: 'Documento principal',
            collection: 'documents',
            type: 'pdf',
            sortOrder: 3,
        );

        $pivot = $owner->fileables()->sole();

        $this->assertSame($file->id, $pivot->file_id);
        $this->assertSame('documents', $pivot->collection);
        $this->assertSame('pdf', $pivot->type);
        $this->assertSame('Documento principal', $pivot->description);
        $this->assertSame(3, $pivot->sort_order);
        $this->assertNotEmpty($pivot->uuid);
    }

    public function test_attach_file_updates_existing_link_and_preserves_uuid(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile(
            file: $file,
            description: 'Descrição inicial',
            collection: 'documents',
            type: 'pdf',
            sortOrder: 1,
        );

        $originalUuid = $owner->fileables()->sole()->uuid;

        $owner->attachFile(
            file: $file,
            description: 'Descrição atualizada',
            collection: 'documents',
            type: 'pdf',
            sortOrder: 5,
        );

        $pivot = $owner->fileables()->sole();

        $this->assertSame($originalUuid, $pivot->uuid);
        $this->assertSame('Descrição atualizada', $pivot->description);
        $this->assertSame(5, $pivot->sort_order);
        $this->assertSame(1, $owner->fileables()->count());
    }

    public function test_morph_to_one_file_returns_the_latest_file_in_its_collection(): void
    {
        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $latestFile = $this->createFile('latest.pdf');

        $owner->attachFile(
            file: $firstFile,
            collection: 'profile',
        );

        $owner->attachFile(
            file: $latestFile,
            collection: 'profile',
        );

        $relation = $owner->morphToOneFile('profile');

        $this->assertInstanceOf(MorphToOneFile::class, $relation);
        $this->assertSame($latestFile->id, $relation->first()->id);
    }

    public function test_morph_to_one_file_returns_null_when_collection_is_empty(): void
    {
        $owner = $this->createOwner();

        $this->assertNull(
            $owner->morphToOneFile('profile')->first()
        );
    }

    public function test_morph_to_one_file_supports_eager_loading(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile(
            file: $file,
            collection: 'profile',
        );

        $loadedOwner = $owner->newQuery()
            ->with('profileFile')
            ->findOrFail($owner->id);

        $this->assertInstanceOf(File::class, $loadedOwner->profileFile);
        $this->assertSame($file->id, $loadedOwner->profileFile->id);
    }

    public function test_set_file_replaces_the_existing_file_in_the_collection(): void
    {
        $owner = $this->createOwner();

        $oldFile = $this->createFile('old.pdf');
        $newFile = $this->createFile('new.pdf');

        $owner->attachFile($oldFile, collection: 'profile');
        $owner->setFile($newFile, collection: 'profile');

        $this->assertSame(
            [$newFile->id],
            $owner->fresh()->filesByCollection('profile')->pluck('files.id')->all(),
        );

        $this->assertDatabaseHas('files', ['id' => $oldFile->id]);
        $this->assertDatabaseHas('files', ['id' => $newFile->id]);
    }

    public function test_set_file_keeps_the_existing_file_when_it_is_the_same_file(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile(
            $file,
            description: 'Descrição original',
            collection: 'profile',
        );

        $originalPivotId = $owner->fileables()->sole()->id;

        $owner->setFile(
            $file,
            collection: 'profile',
            description: 'Descrição atualizada',
        );

        $pivot = $owner->fileables()->sole();

        $this->assertSame($originalPivotId, $pivot->id);
        $this->assertSame('Descrição atualizada', $pivot->description);
        $this->assertSame(1, $owner->fileables()->count());
    }

    public function test_set_file_does_not_replace_files_in_other_collections(): void
    {
        $owner = $this->createOwner();

        $profileFile = $this->createFile('profile.pdf');
        $documentFile = $this->createFile('document.pdf');
        $newProfileFile = $this->createFile('new-profile.pdf');

        $owner->attachFile($profileFile, collection: 'profile');
        $owner->attachFile($documentFile, collection: 'documents');

        $owner->setFile($newProfileFile, collection: 'profile');

        $this->assertSame(
            [$newProfileFile->id],
            $owner->fresh()->filesByCollection('profile')->pluck('files.id')->all(),
        );

        $this->assertSame(
            [$documentFile->id],
            $owner->fresh()->filesByCollection('documents')->pluck('files.id')->all(),
        );
    }

    public function test_detach_file_removes_only_the_selected_file_link(): void
    {
        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');

        $owner->attachFile($firstFile, collection: 'documents');
        $owner->attachFile($secondFile, collection: 'documents');

        $owner->detachFile($firstFile);

        $this->assertDatabaseMissing('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $firstFile->id,
        ]);

        $this->assertDatabaseHas('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $secondFile->id,
        ]);
    }

    public function test_detach_from_collection_can_remove_only_selected_files(): void
    {
        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');
        $otherFile = $this->createFile('other.pdf');

        $owner->attachFile($firstFile, collection: 'documents');
        $owner->attachFile($secondFile, collection: 'documents');
        $owner->attachFile($otherFile, collection: 'profile');

        $owner->detachFromCollection('documents', [$firstFile->id]);

        $this->assertDatabaseMissing('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $firstFile->id,
        ]);

        $this->assertDatabaseHas('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $secondFile->id,
        ]);

        $this->assertDatabaseHas('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $otherFile->id,
        ]);
    }

    public function test_clear_files_can_clear_only_one_collection(): void
    {
        $owner = $this->createOwner();

        $documentFile = $this->createFile('document.pdf');
        $profileFile = $this->createFile('profile.pdf');

        $owner->attachFile($documentFile, collection: 'documents');
        $owner->attachFile($profileFile, collection: 'profile');

        $owner->clearFiles('documents');

        $this->assertFalse($owner->hasFiles('documents'));
        $this->assertTrue($owner->hasFiles('profile'));
    }

    public function test_attach_files_assigns_sequential_sort_order(): void
    {
        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');
        $thirdFile = $this->createFile('third.pdf');

        $owner->attachFiles(
            [$firstFile, $secondFile, $thirdFile],
            collection: 'documents',
        );

        $this->assertSame(
            [0, 1, 2],
            $owner->fileables()
                ->where('collection', 'documents')
                ->orderBy('sort_order')
                ->pluck('sort_order')
                ->all(),
        );
    }

    public function test_ordered_files_returns_files_by_sort_order(): void
    {
        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');

        $owner->attachFile($secondFile, collection: 'documents', sortOrder: 2);
        $owner->attachFile($firstFile, collection: 'documents', sortOrder: 1);

        $this->assertSame(
            [$firstFile->id, $secondFile->id],
            $owner->getFiles('documents', ordered: true)->pluck('id')->all(),
        );
    }

    public function test_attach_files_rejects_items_that_are_not_file_instances(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'All items must be instances of File.',
        );

        $owner->attachFiles([$file, 'invalid-item'], collection: 'documents');
    }

    public function test_set_file_deletes_replaced_orphan_after_commit(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', true);

        $owner = $this->createOwner();

        $oldFile = $this->createFile('old.pdf');
        $newFile = $this->createFile('new.pdf');

        $owner->attachFile($oldFile, collection: 'profile');

        $owner->setFile($newFile, collection: 'profile');

        $this->assertDatabaseMissing('files', [
            'id' => $oldFile->id,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $newFile->id,
        ]);
    }

    public function test_set_file_does_not_delete_a_replaced_file_still_attached_to_another_owner(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', true);

        $firstOwner = $this->createOwner('First owner');
        $secondOwner = $this->createOwner('Second owner');

        $sharedFile = $this->createFile('shared.pdf');
        $replacementFile = $this->createFile('replacement.pdf');

        $firstOwner->attachFile($sharedFile, collection: 'profile');
        $secondOwner->attachFile($sharedFile, collection: 'documents');

        $firstOwner->setFile($replacementFile, collection: 'profile');

        $this->assertDatabaseHas('files', [
            'id' => $sharedFile->id,
        ]);

        $this->assertDatabaseHas('fileables', [
            'file_id' => $sharedFile->id,
            'fileable_id' => $secondOwner->id,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $replacementFile->id,
        ]);
    }

    public function test_detach_file_deletes_orphan_file_when_auto_delete_is_enabled(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', true);

        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents');
        $owner->detachFile($file);

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertDatabaseMissing('fileables', ['file_id' => $file->id]);
    }

    public function test_detach_file_preserves_file_attached_to_another_owner(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', true);

        $firstOwner = $this->createOwner('First owner');
        $secondOwner = $this->createOwner('Second owner');
        $file = $this->createFile();

        $firstOwner->attachFile($file, collection: 'documents');
        $secondOwner->attachFile($file, collection: 'documents');

        $firstOwner->detachFile($file);

        $this->assertDatabaseHas('files', ['id' => $file->id]);
        $this->assertDatabaseHas('fileables', [
            'file_id' => $file->id,
            'fileable_id' => $secondOwner->id,
        ]);
    }

    public function test_detach_from_collection_deletes_orphan_files_when_enabled(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', true);

        $owner = $this->createOwner();
        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');
        $otherFile = $this->createFile('other.pdf');

        $owner->attachFile($firstFile, collection: 'documents');
        $owner->attachFile($secondFile, collection: 'documents');
        $owner->attachFile($otherFile, collection: 'profile');

        $owner->detachFromCollection('documents');

        $this->assertDatabaseMissing('files', ['id' => $firstFile->id]);
        $this->assertDatabaseMissing('files', ['id' => $secondFile->id]);
        $this->assertDatabaseHas('files', ['id' => $otherFile->id]);
    }

    public function test_clear_files_deletes_orphan_files_from_all_collections_when_enabled(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', true);

        $owner = $this->createOwner();
        $documentFile = $this->createFile('document.pdf');
        $profileFile = $this->createFile('profile.pdf');

        $owner->attachFile($documentFile, collection: 'documents');
        $owner->attachFile($profileFile, collection: 'profile');

        $owner->clearFiles();

        $this->assertDatabaseMissing('files', ['id' => $documentFile->id]);
        $this->assertDatabaseMissing('files', ['id' => $profileFile->id]);
        $this->assertDatabaseCount('fileables', 0);
    }

    public function test_update_file_description_can_target_a_specific_collection(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile(
            $file,
            description: 'Descrição dos documentos',
            collection: 'documents',
        );

        $owner->attachFile(
            $file,
            description: 'Descrição do perfil',
            collection: 'profile',
        );

        $owner->updateFileDescription(
            $file,
            'Descrição atualizada',
            collection: 'documents',
        );

        $descriptions = $owner->fileables()
            ->orderBy('collection')
            ->pluck('description', 'collection')
            ->all();

        $this->assertSame('Descrição atualizada', $descriptions['documents']);
        $this->assertSame('Descrição do perfil', $descriptions['profile']);
    }

    public function test_first_file_and_last_file_return_files_by_attachment_order(): void
    {
        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');
        $thirdFile = $this->createFile('third.pdf');

        $owner->attachFile($firstFile, collection: 'documents');
        $owner->attachFile($secondFile, collection: 'documents');
        $owner->attachFile($thirdFile, collection: 'documents');

        $this->assertSame($firstFile->id, $owner->firstFile('documents')?->id);
        $this->assertSame($thirdFile->id, $owner->lastFile('documents')?->id);
        $this->assertSame($firstFile->id, $owner->firstFile()?->id);
        $this->assertSame($thirdFile->id, $owner->lastFile()?->id);
    }

    public function test_has_files_and_get_files_support_collection_filtering(): void
    {
        $owner = $this->createOwner();

        $documentFile = $this->createFile('document.pdf');
        $profileFile = $this->createFile('profile.pdf');

        $owner->attachFile($documentFile, collection: 'documents');
        $owner->attachFile($profileFile, collection: 'profile');

        $this->assertTrue($owner->hasFiles());
        $this->assertTrue($owner->hasFiles('documents'));
        $this->assertFalse($owner->hasFiles('invoices'));

        $this->assertCount(2, $owner->getFiles());
        $this->assertCount(1, $owner->getFiles('documents'));
        $this->assertSame(
            [$documentFile->id],
            $owner->getFiles('documents')->pluck('id')->all(),
        );
    }

    public function test_attach_file_allows_same_file_in_different_collections(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents');
        $owner->attachFile($file, collection: 'profile');

        $this->assertSame(2, $owner->fileables()->count());
        $this->assertTrue($owner->hasFiles('documents'));
        $this->assertTrue($owner->hasFiles('profile'));
    }

    public function test_update_file_description_without_collection_updates_all_matching_links(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile(
            $file,
            description: 'Descrição inicial',
            collection: 'documents',
        );

        $owner->attachFile(
            $file,
            description: 'Outra descrição',
            collection: 'profile',
        );

        $owner->updateFileDescription($file, 'Descrição global');

        $this->assertSame(
            ['Descrição global'],
            $owner->fileables()
                ->where('file_id', $file->id)
                ->distinct()
                ->pluck('description')
                ->all(),
        );

        $this->assertSame(
            2,
            $owner->fileables()
                ->where('file_id', $file->id)
                ->where('description', 'Descrição global')
                ->count(),
        );
    }

    public function test_clear_files_preserves_file_records_when_orphan_deletion_is_disabled(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', false);

        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents');
        $owner->clearFiles();

        $this->assertDatabaseHas('files', ['id' => $file->id]);
        $this->assertDatabaseMissing('fileables', ['file_id' => $file->id]);
    }

    public function test_set_file_replaces_only_the_matching_type(): void
    {
        $owner = $this->createOwner();

        $profilePhoto = $this->createFile('profile.jpg');
        $referencePhoto = $this->createFile('reference.jpg');
        $replacementPhoto = $this->createFile('new-profile.jpg');

        $owner->attachFile(
            $profilePhoto,
            collection: 'photos',
            type: 'foto_perfil',
        );

        $owner->attachFile(
            $referencePhoto,
            collection: 'photos',
            type: 'foto_referencia',
        );

        $owner->setFile(
            $replacementPhoto,
            collection: 'photos',
            type: 'foto_perfil',
        );

        $this->assertDatabaseMissing('fileables', [
            'file_id' => $profilePhoto->id,
        ]);

        $this->assertDatabaseHas('fileables', [
            'file_id' => $referencePhoto->id,
            'collection' => 'photos',
            'type' => 'foto_referencia',
        ]);

        $this->assertDatabaseHas('fileables', [
            'file_id' => $replacementPhoto->id,
            'collection' => 'photos',
            'type' => 'foto_perfil',
        ]);
    }

    public function test_morph_to_one_file_filters_by_type(): void
    {
        $owner = $this->createOwner();

        $pdfFile = $this->createFile('document.pdf');
        $imageFile = $this->createFile('document.png');

        $owner->attachFile(
            $pdfFile,
            collection: 'documents',
            type: 'pdf',
        );

        $owner->attachFile(
            $imageFile,
            collection: 'documents',
            type: 'image',
        );

        $this->assertSame(
            $pdfFile->id,
            $owner->morphToOneFile('documents', 'pdf')->first()?->id,
        );

        $this->assertSame(
            $imageFile->id,
            $owner->morphToOneFile('documents', 'image')->first()?->id,
        );

        $this->assertNull(
            $owner->morphToOneFile('documents', 'video')->first(),
        );
    }

    public function test_soft_deleting_owner_preserves_file_links(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents');

        $owner->delete();

        $this->assertSoftDeleted('file_manager_test_owners', [
            'id' => $owner->id,
        ]);

        $this->assertDatabaseHas('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $file->id,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
        ]);
    }

    public function test_force_deleting_owner_removes_file_links(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents');

        $owner->forceDelete();

        $this->assertDatabaseMissing('file_manager_test_owners', [
            'id' => $owner->id,
        ]);

        $this->assertDatabaseMissing('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $file->id,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
        ]);
    }

    public function test_attach_file_does_not_duplicate_existing_link(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents', type: 'relatorio');
        $owner->attachFile($file, collection: 'documents', type: 'relatorio');

        $this->assertSame(1, $owner->fileables()->count());
    }

    public function test_detach_file_does_not_delete_orphan_when_auto_delete_is_disabled(): void
    {
        config()->set('file-manager.auto_delete_orphan_file', false);

        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents');
        $owner->detachFile($file);

        $this->assertDatabaseHas('files', ['id' => $file->id]);
        $this->assertDatabaseMissing('fileables', ['file_id' => $file->id]);
    }

    public function test_delete_orphan_files_command_dry_run_preserves_records(): void
    {
        $orphan = $this->createFile('orphan.pdf');

        $this->artisan('file-manager:delete-orphan-files', [
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Modo dry-run')
            ->assertSuccessful();

        $this->assertDatabaseHas('files', [
            'id' => $orphan->id,
        ]);
    }

    public function test_delete_orphan_files_command_deletes_orphans_when_forced(): void
    {
        config()->set('file-manager.auto_delete_physical_file', false);

        $orphan = $this->createFile('orphan.pdf');

        $owner = $this->createOwner();
        $attachedFile = $this->createFile('attached.pdf');

        $owner->attachFile($attachedFile, collection: 'documents');

        $this->artisan('file-manager:delete-orphan-files', [
            '--force' => true,
        ])
            ->assertSuccessful();

        $this->assertDatabaseMissing('files', [
            'id' => $orphan->id,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $attachedFile->id,
        ]);
    }

    public function test_delete_orphan_files_command_preserves_all_attached_files(): void
    {
        config()->set('file-manager.auto_delete_physical_file', false);

        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');

        $owner->attachFile($firstFile, collection: 'documents');
        $owner->attachFile($secondFile, collection: 'reports');

        $this->artisan('file-manager:delete-orphan-files', [
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('files', [
            'id' => $firstFile->id,
        ]);

        $this->assertDatabaseHas('files', [
            'id' => $secondFile->id,
        ]);

        $this->assertDatabaseCount('fileables', 2);
    }

    public function test_delete_orphan_files_command_handles_empty_orphan_list(): void
    {
        config()->set('file-manager.auto_delete_physical_file', false);

        $owner = $this->createOwner();
        $file = $this->createFile('attached.pdf');

        $owner->attachFile($file, collection: 'documents');

        $this->artisan('file-manager:delete-orphan-files', [
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
        ]);

        $this->assertDatabaseHas('fileables', [
            'file_id' => $file->id,
        ]);
    }

    public function test_fileables_returns_morph_many_relation(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->attachFile($file, collection: 'documents');

        $this->assertSame(1, $owner->fileables()->count());
        $this->assertSame($file->id, $owner->fileables()->first()->file_id);
    }

    public function test_files_by_collection_returns_only_files_from_selected_collection(): void
    {
        $owner = $this->createOwner();

        $document = $this->createFile('document.pdf');
        $image = $this->createFile('image.jpg');

        $owner->attachFile($document, collection: 'documents');
        $owner->attachFile($image, collection: 'images');

        $files = $owner->filesByCollection('documents')->get();

        $this->assertCount(1, $files);
        $this->assertSame($document->id, $files->first()->id);
    }

    public function test_attach_files_attaches_multiple_files(): void
    {
        $owner = $this->createOwner();

        $firstFile = $this->createFile('first.pdf');
        $secondFile = $this->createFile('second.pdf');
        $thirdFile = $this->createFile('third.pdf');

        $owner->attachFiles(
            [$firstFile, $secondFile, $thirdFile],
            collection: 'documents',
        );

        $this->assertDatabaseCount('fileables', 3);

        $this->assertEqualsCanonicalizing(
            [$firstFile->id, $secondFile->id, $thirdFile->id],
            $owner->fileables()->pluck('file_id')->all(),
        );
    }

    public function test_update_file_description_does_not_create_a_link_when_file_is_not_attached(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile();

        $owner->updateFileDescription(
            $file,
            'Descrição atualizada',
            collection: 'documents',
        );

        $this->assertDatabaseMissing('fileables', [
            'fileable_id' => $owner->id,
            'file_id' => $file->id,
            'collection' => 'documents',
        ]);
    }
}