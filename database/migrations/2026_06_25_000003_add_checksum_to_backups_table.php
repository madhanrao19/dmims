<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs integrity verification: the checksum is computed over the encrypted
 * backup file at write time and re-checked before restore, so a corrupted or
 * tampered backup is rejected instead of silently restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('checksum', 64)->nullable()->after('file_size');
            $table->boolean('verified')->default(false)->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn(['checksum', 'verified']);
        });
    }
};
