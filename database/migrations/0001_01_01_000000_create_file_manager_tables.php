<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('disk')
                ->default('local')
                ->index();
            $table->string('path', 2048);
            $table->string('visibility')
                ->default('private')
                ->index();

            $table->string('name', 512)->nullable();
            $table->string('original_name', 512);

            $table->string('extension', 20)->nullable();
            $table->string('mime_type')->nullable();

            $table->unsignedBigInteger('size')->nullable();

            $table->text('description')->nullable();

            $table->string('hash', 64)->nullable();

            $table->string('creator_type')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('mime_type');
            $table->index('hash');
            $table->index(
                ['creator_type', 'creator_id'],
                'files_creator_index'
            );

            $table->unique([
                'disk',
                'path'
            ]);
        });

        Schema::create('fileables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('file_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->morphs('fileable');

            $table->string('collection');

            $table->string('type')->nullable();

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index('collection');

            $table->index([
                'fileable_type',
                'fileable_id',
                'collection',
                'type'
            ], 'fileables_lookup_index');

            $table->unique([
                'file_id',
                'fileable_type',
                'fileable_id',
                'collection',
                'type'
            ], 'fileables_unique');
        });

        Schema::create('file_shares', function (Blueprint $table) {
            $table->id();

            $table->foreignId('file_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->uuid('token')->unique();


            $table->timestamp('expires_at')
                ->nullable()
                ->index();

            $table->timestamp('revoked_at')
                ->nullable();

            $table->timestamp('last_accessed_at')
                ->nullable();

            $table->unsignedBigInteger('access_count')
                ->default(0);

            $table->string('creator_type')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();

            

            $table->timestamps();

            $table->index('file_id');
            $table->index(
                ['creator_type', 'creator_id'],
                'file_shares_creator_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('file_shares');
        Schema::dropIfExists('fileables');
        Schema::dropIfExists('files');
    }
};
