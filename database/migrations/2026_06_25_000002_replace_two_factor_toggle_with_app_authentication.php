<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `two_factor_enabled` column was a UI-only toggle with no enrollment,
 * secret, challenge, or recovery-code flow behind it. Replace it with
 * Filament's built-in app-authentication (TOTP) columns, which are backed by
 * a real enroll/challenge/recovery implementation (Filament\Auth\MultiFactor\App).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable()->after('two_factor_enabled');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
