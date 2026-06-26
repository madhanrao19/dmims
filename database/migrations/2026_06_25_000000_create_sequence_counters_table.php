<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs SequenceGenerator: a single row-locked counter table replaces the
 * count()+1 numbering that collided under concurrent writes (stock/document
 * movement numbers, barcode sequences).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequence_counters', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_counters');
    }
};
