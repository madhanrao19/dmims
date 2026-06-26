<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->string('borrowed_by')->nullable()->after('destination');
            $table->date('due_date')->nullable()->after('borrowed_by');
            $table->timestamp('returned_at')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->dropColumn(['borrowed_by', 'due_date', 'returned_at']);
        });
    }
};
