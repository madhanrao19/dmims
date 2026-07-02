<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('company_code')->unique();
            $table->string('registration_no')->nullable();
            $table->string('tin_no')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['trial', 'active', 'near_expiry', 'expired', 'suspended', 'cancelled', 'archived'])->default('trial');
            $table->string('deployment_type')->default('DatamationOnPremHosted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_code')->unique();
            $table->string('module_name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('customer_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('module_id')->constrained('modules');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'module_id']);
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_code')->unique();
            $table->string('plan_name');
            $table->text('description')->nullable();
            $table->integer('max_users')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_document_files')->nullable();
            $table->integer('max_boxes')->nullable();
            $table->json('allowed_reports')->nullable();
            $table->json('enabled_modules')->nullable();
            $table->string('support_level')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('billing_cycle', ['monthly', 'yearly', 'custom'])->default('yearly');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans');
            $table->string('subscription_no')->unique();
            $table->date('valid_from');
            $table->date('valid_to');
            $table->integer('grace_period_days')->default(0);
            $table->integer('max_users')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_document_files')->nullable();
            $table->integer('max_boxes')->nullable();
            $table->json('allowed_reports')->nullable();
            $table->json('enabled_modules')->nullable();
            $table->string('support_level')->nullable();
            $table->enum('status', ['trial', 'active', 'near_expiry', 'expired_grace', 'restricted', 'suspended', 'cancelled'])->default('trial');
            $table->text('renewal_notes')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('license_no')->unique();
            $table->string('deployment_mode')->default('DatamationOnPremHosted');
            $table->string('license_mode')->default('InternalSubscription');
            $table->string('installation_id')->nullable();
            $table->string('server_fingerprint')->nullable();
            $table->date('valid_from');
            $table->date('valid_to');
            $table->integer('grace_period_days')->default(0);
            $table->integer('max_users')->nullable();
            $table->integer('max_products')->nullable();
            $table->integer('max_document_files')->nullable();
            $table->integer('max_boxes')->nullable();
            $table->json('enabled_modules')->nullable();
            $table->json('allowed_reports')->nullable();
            $table->enum('status', ['trial', 'active', 'near_expiry', 'expired', 'restricted', 'suspended', 'cancelled'])->default('trial');
            $table->text('signature')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });

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

        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('type_code')->unique();
            $table->string('type_name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('parent_id')->nullable()->constrained('locations');
            $table->foreignId('location_type_id')->nullable()->constrained('location_types');
            $table->string('location_code');
            $table->string('location_name');
            $table->text('full_path')->nullable();
            $table->string('barcode')->nullable();
            $table->boolean('can_store_stock')->default(true);
            $table->boolean('can_store_boxes')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['customer_id', 'location_code']);
            $table->unique(['customer_id', 'barcode']);
            $table->index(['customer_id', 'parent_id']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('barcode_registry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('barcode', 150);
            $table->enum('barcode_type', ['product', 'location', 'box', 'document_file']);
            $table->string('reference_table');
            $table->unsignedBigInteger('reference_id');
            $table->enum('status', ['active', 'inactive', 'retired'])->default('active');
            $table->integer('printed_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['customer_id', 'barcode']);
            $table->index(['customer_id', 'barcode_type']);
            $table->index(['reference_table', 'reference_id']);
        });

        Schema::create('barcode_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('barcode', 150);
            $table->string('barcode_type')->nullable();
            $table->string('reference_table')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->enum('scan_result', ['found', 'unknown', 'inactive', 'permission_denied'])->default('found');
            $table->string('action_taken')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users');
            $table->timestamp('scanned_at')->nullable();
            $table->string('ip_address', 100)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('category_code')->nullable();
            $table->string('category_name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('sku');
            $table->string('barcode', 150)->nullable();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->foreignId('default_location_id')->nullable()->constrained('locations');
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->enum('status', ['active', 'inactive', 'discontinued'])->default('active');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['customer_id', 'sku']);
            $table->unique(['customer_id', 'barcode']);
        });

        Schema::create('product_location_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('location_id')->constrained('locations');
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->decimal('available_quantity', 18, 4)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
            // Explicit short name: the auto-generated
            // "product_location_stocks_customer_id_product_id_location_id_unique"
            // is 65 chars and exceeds MySQL/MariaDB's 64-char identifier limit.
            $table->unique(['customer_id', 'product_id', 'location_id'], 'product_location_stocks_cpl_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('movement_no');
            $table->foreignId('product_id')->constrained('products');
            $table->enum('movement_type', ['opening_balance', 'stock_in', 'stock_out', 'transfer', 'adjustment', 'return', 'disposal']);
            $table->foreignId('from_location_id')->nullable()->constrained('locations');
            $table->foreignId('to_location_id')->nullable()->constrained('locations');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('reference_no')->nullable();
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'movement_no']);
            $table->index(['customer_id', 'product_id']);
            $table->index(['customer_id', 'movement_type']);
            $table->index(['customer_id', 'performed_at']);
        });

        Schema::create('stock_adjustment_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('stock_movement_id')->constrained('stock_movements');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->enum('alert_type', ['low_stock', 'out_of_stock', 'overstock'])->default('low_stock');
            $table->decimal('threshold_quantity', 18, 4)->nullable();
            $table->decimal('current_quantity', 18, 4)->nullable();
            $table->enum('status', ['open', 'acknowledged', 'closed'])->default('open');
            $table->timestamps();
        });

        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('box_barcode', 150)->unique();
            $table->string('box_number', 100)->unique();
            $table->foreignId('current_location_id')->constrained('locations');
            $table->string('source_origin')->nullable();
            $table->integer('capacity_limit')->nullable();
            $table->integer('current_file_count')->default(0);
            $table->enum('status', ['active', 'closed', 'moved_out', 'archived', 'damaged', 'missing'])->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('type_code');
            $table->string('type_name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('file_barcode', 150)->unique();
            $table->string('file_reference_no', 150)->nullable();
            $table->string('title');
            $table->foreignId('document_type_id')->nullable()->constrained('document_types');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->string('owner_name')->nullable();
            $table->foreignId('current_box_id')->constrained('boxes');
            $table->enum('current_status', ['active', 'transferred', 'moved_out', 'archived', 'missing', 'damaged', 'closed'])->default('active');
            $table->string('source_origin')->nullable();
            $table->string('destination')->nullable();
            $table->date('received_date')->nullable();
            $table->date('archived_date')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'file_reference_no']);
            $table->index(['customer_id', 'current_box_id']);
            $table->index(['customer_id', 'current_status']);
        });

        Schema::create('document_movement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('movement_no');
            $table->enum('movable_type', ['document_file', 'box']);
            $table->unsignedBigInteger('movable_id');
            $table->enum('action_type', ['create', 'transfer_file', 'transfer_box', 'move_out', 'return', 'archive', 'correction']);
            $table->foreignId('from_location_id')->nullable()->constrained('locations');
            $table->foreignId('to_location_id')->nullable()->constrained('locations');
            $table->foreignId('from_box_id')->nullable()->constrained('boxes');
            $table->foreignId('to_box_id')->nullable()->constrained('boxes');
            $table->string('source_origin')->nullable();
            $table->string('destination')->nullable();
            $table->string('scanned_barcode', 150)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'movement_no']);
            // Explicit short name (the auto-generated one is 64 chars — at the
            // MySQL/MariaDB limit; name it to keep a safety margin).
            $table->index(['customer_id', 'movable_type', 'movable_id'], 'doc_movement_logs_movable_index');
            $table->index(['customer_id', 'action_type']);
            $table->index(['customer_id', 'performed_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('module');
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 100)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('support_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('support_user_id')->constrained('users');
            $table->foreignId('target_user_id')->nullable()->constrained('users');
            $table->text('reason');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('backup_no')->unique();
            $table->enum('backup_type', ['database', 'files', 'full', 'snapshot']);
            $table->string('storage_location')->nullable();
            $table->text('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'restored'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('import_no');
            $table->enum('import_type', ['products', 'boxes', 'document_files']);
            $table->string('file_name');
            $table->text('file_path')->nullable();
            $table->enum('status', ['uploaded', 'validating', 'validated', 'processing', 'completed', 'failed'])->default('uploaded');
            $table->integer('total_rows')->default(0);
            $table->integer('success_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports');
            $table->integer('row_number');
            $table->json('row_data');
            $table->enum('validation_status', ['pending', 'valid', 'invalid', 'imported', 'failed'])->default('pending');
            $table->json('error_messages')->nullable();
            $table->timestamps();
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('export_no');
            $table->string('export_type');
            $table->string('file_name')->nullable();
            $table->text('file_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('notification_type');
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('setting_group');
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->enum('setting_type', ['string', 'number', 'boolean', 'json'])->default('string');
            $table->timestamps();
            $table->unique(['customer_id', 'setting_group', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('exports');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('support_access_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('document_movement_logs');
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('boxes');
        Schema::dropIfExists('stock_alerts');
        Schema::dropIfExists('stock_adjustment_approvals');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_location_stocks');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('barcode_scan_logs');
        Schema::dropIfExists('barcode_registry');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('location_types');
        Schema::dropIfExists('license_logs');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('customer_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('customer_modules');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('customers');
    }
};
