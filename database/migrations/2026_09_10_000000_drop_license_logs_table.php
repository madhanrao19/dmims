<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the license_logs table (created in
 * 2026_05_22_060000_create_dmims_platform_tables.php). Unlike its sibling
 * subscription_logs (actively populated by CustomerSubscriptionObserver),
 * no observer ever wrote to license_logs — confirmed via a full-repo grep
 * before removal. License already has a complete audit trail via the
 * Auditable trait (app/Models/Concerns/Auditable.php), which records every
 * create/update/delete with old/new diffs into the platform-wide audit_logs
 * table, visible through the existing AuditLogResource. license_logs was
 * modeled but redundant scaffolding, never wired up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('license_logs');
    }

    public function down(): void
    {
        Schema::create('license_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('license_id')->nullable()->constrained('licenses');
            $table->string('action');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });
    }
};
