<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A moved-out box has no location and a moved-out file has no box (TDD §20:
 * items can leave the system). The original schema made these foreign keys
 * NOT NULL, which prevented move-out — relax them to nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('current_location_id')->nullable()->change();
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->foreignId('current_box_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('current_location_id')->nullable(false)->change();
        });

        Schema::table('document_files', function (Blueprint $table) {
            $table->foreignId('current_box_id')->nullable(false)->change();
        });
    }
};
