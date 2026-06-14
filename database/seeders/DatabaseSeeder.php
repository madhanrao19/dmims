<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\Module;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles & permissions are required for non-platform users to have access.
        // Kept in a separate seeder so production can run it without demo data.
        $this->call(RolesAndPermissionsSeeder::class);

        $adminRole = Role::findByName('Datamation Super Admin');

        // --- Demo customer, plan, modules and subscription ---
        $customer = Customer::firstOrCreate(
            ['company_code' => 'DEMO'],
            [
                'company_name' => 'Datamation Inventory Demo',
                'contact_person' => 'Admin',
                'email' => 'admin@datamation.example',
                'phone' => '+1234567890',
                'address' => '123 Demo Street',
                'status' => 'active',
            ]
        );

        $plan = SubscriptionPlan::firstOrCreate(
            ['plan_code' => 'free-trial'],
            [
                'plan_name' => 'Free Trial',
                'description' => 'Starter plan for evaluation and onboarding',
                'price' => 0,
                'billing_cycle' => 'monthly',
                'status' => 'active',
            ]
        );

        // Module catalogue per the Database Dictionary (§3).
        $modules = [
            'stock_inventory' => 'Stock Inventory',
            'document_tracking' => 'Document Tracking',
            'barcode_scanning' => 'Barcode Scanning',
            'barcode_printing' => 'Barcode Printing',
            'reports' => 'Reports',
            'billing_view' => 'Billing View',
        ];

        $moduleModels = [];
        foreach ($modules as $code => $name) {
            $moduleModels[$code] = Module::firstOrCreate(
                ['module_code' => $code],
                ['module_name' => $name, 'description' => $name, 'status' => 'active']
            );
        }

        // Enable the demo customer's modules.
        foreach (['stock_inventory', 'document_tracking', 'barcode_scanning', 'reports'] as $code) {
            CustomerModule::firstOrCreate(
                ['customer_id' => $customer->id, 'module_id' => $moduleModels[$code]->id],
                ['is_enabled' => true, 'enabled_at' => now()]
            );
        }

        CustomerSubscription::firstOrCreate(
            ['customer_id' => $customer->id, 'subscription_plan_id' => $plan->id],
            [
                'subscription_no' => 'SUB-0001',
                'valid_from' => now(),
                'valid_to' => now()->addDays(30),
                'grace_period_days' => 7,
                'max_users' => 25,
                'max_products' => 500,
                'max_document_files' => 1000,
                'max_boxes' => 200,
                'allowed_reports' => ['inventory', 'usage', 'audit'],
                'enabled_modules' => ['stock_inventory', 'document_tracking', 'barcode_scanning', 'reports'],
                'support_level' => 'standard',
                'status' => 'active',
            ]
        );

        // --- Platform administrator (idempotent) ---
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Platform Admin',
                'password' => bcrypt('password'),
                'customer_id' => $customer->id,
                'status' => 'active',
                'is_platform_user' => true,
            ]
        );

        $admin->syncRoles([$adminRole]);
    }
}
