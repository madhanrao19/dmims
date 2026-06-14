<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('customer_subscription_id')->nullable()->constrained('customer_subscriptions')->cascadeOnDelete();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'customer_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_logs');
    }
};
