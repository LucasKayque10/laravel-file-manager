<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fileables', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique();
        });

        DB::table('fileables')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($fileables) {
                foreach ($fileables as $fileable) {
                    DB::table('fileables')
                        ->where('id', $fileable->id)
                        ->update([
                            'uuid' => (string) Str::uuid(),
                        ]);
                }
            });

        Schema::table('fileables', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('fileables', function (Blueprint $table) {
            $table->dropUnique('fileables_uuid_unique');
            $table->dropColumn('uuid');
        });
    }
};