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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $customer = Customer::create([
            'company_name' => 'Datamation Inventory Demo',
            'contact_name' => 'Admin',
            'email' => 'admin@datamation.example',
            'phone' => '+1234567890',
            'address' => '123 Demo Street',
            'city' => 'Demo City',
            'state' => 'Demo State',
            'country' => 'Demo Country',
            'status' => 'active',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $managerRole = Role::firstOrCreate(['name' => 'manager']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        $permissions = [
            'manage customers',
            'manage users',
            'manage inventory',
            'manage documents',
            'manage subscriptions',
            'manage licensing',
            'manage settings',
            'manage modules',
            'view reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole->syncPermissions($permissions);

        $plan = SubscriptionPlan::firstOrCreate(
            ['name' => 'Free Trial'],
            ['description' => 'Starter plan for evaluation and onboarding', 'price' => 0, 'billing_cycle' => 'monthly', 'status' => 'active']
        );

        $inventoryModule = Module::firstOrCreate(['name' => 'Inventory'], ['description' => 'Inventory management', 'status' => 'active', 'module_code' => 'inventory']);
        $documentsModule = Module::firstOrCreate(['name' => 'Documents'], ['description' => 'Document management', 'status' => 'active', 'module_code' => 'documents']);

        CustomerModule::firstOrCreate([
            'customer_id' => $customer->id,
            'module_id' => $inventoryModule->id,
        ], [
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        CustomerModule::firstOrCreate([
            'customer_id' => $customer->id,
            'module_id' => $documentsModule->id,
        ], [
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        CustomerSubscription::firstOrCreate([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
        ], [
            'subscription_no' => 'SUB-0001',
            'valid_from' => now(),
            'valid_to' => now()->addDays(30),
            'grace_period_days' => 7,
            'max_users' => 25,
            'max_products' => 500,
            'max_document_files' => 1000,
            'max_boxes' => 200,
            'allowed_reports' => ['inventory', 'usage', 'audit'],
            'enabled_modules' => ['inventory', 'documents'],
            'support_level' => 'standard',
            'status' => 'active',
        ]);

        User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'customer_id' => $customer->id,
            'status' => 'active',
            'is_platform_user' => true,
        ])->assignRole($adminRole);
    }
}
