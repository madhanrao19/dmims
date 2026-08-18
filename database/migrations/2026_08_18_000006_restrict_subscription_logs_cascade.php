<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscription_logs.customer_subscription_id cascade-deleted while the
 * structurally identical license_logs.license_id restricts — inconsistent
 * enforcement of the same audit-first design used for billing_logs
 * (DBA review finding, High #5). Changed to restrict so deleting a
 * subscription can no longer silently wipe its audit history. No data
 * impact: this only changes future delete behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_logs', function (Blueprint $table) {
            $table->dropForeign(['customer_subscription_id']);
            $table->foreign('customer_subscription_id')
                ->references('id')->on('customer_subscriptions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_logs', function (Blueprint $table) {
            $table->dropForeign(['customer_subscription_id']);
            $table->foreign('customer_subscription_id')
                ->references('id')->on('customer_subscriptions')
                ->cascadeOnDelete();
        });
    }
};
