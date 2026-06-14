<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            // Database Dictionary §7: Full / View Only / Blocked.
            $table->enum('technical_access_mode', ['full', 'view_only', 'blocked'])
                ->default('full')
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('technical_access_mode');
        });
    }
};
