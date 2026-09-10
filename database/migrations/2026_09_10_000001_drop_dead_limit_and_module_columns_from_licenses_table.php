<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops max_users/max_products/max_document_files/max_boxes/enabled_modules/
 * allowed_reports from licenses — these duplicate identically-named columns
 * on customer_subscriptions, but only the CustomerSubscription copies were
 * ever read (AccessControlService::getEffectiveLimits() reads
 * CustomerSubscription exclusively; module access comes from CustomerModule,
 * synced from CustomerSubscription via CustomerSubscriptionObserver — never
 * from License). Confirmed via grep: no code outside License's own model/
 * Filament form ever referenced these 6 columns. Per ADR-005, License
 * (technical access) and Subscription (commercial entitlement) remain
 * deliberately separate systems — this migration does not change that
 * split, it only removes dead duplication on the License side.
 *
 * down() restores column structure only — any historical values in these
 * columns are not recoverable once up() runs. Confirmed low-risk in this
 * codebase (no License factory, no seeder sets these fields), but this is
 * still a genuine, one-way data loss for any environment that happened to
 * have them populated — do not run against production without confirming
 * that first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->dropColumn([
                'max_users',
                'max_products',
                'max_document_files',
                'max_boxes',
                'enabled_modules',
                'allowed_reports',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table): void {
            $table->integer('max_users')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_document_files')->nullable();
            $table->integer('max_boxes')->nullable();
            $table->json('enabled_modules')->nullable();
            $table->json('allowed_reports')->nullable();
        });
    }
};
