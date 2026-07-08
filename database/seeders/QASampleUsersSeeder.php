<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * QA-only sample users — one per documented role, weak credentials by design.
 * Never called from DatabaseSeeder; run explicitly:
 *   php artisan db:seed --class=QASampleUsersSeeder
 */
class QASampleUsersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! App::environment(['local', 'testing'])) {
            $this->command?->error('QASampleUsersSeeder only runs in local/testing environments.');

            return;
        }

        // Demo customer, plan, modules and admin come from DatabaseSeeder.
        $this->call(DatabaseSeeder::class);

        $customer = \App\Models\Customer::where('company_code', 'DEMO')->firstOrFail();

        $roleUsers = [
            'Datamation Super Admin' => ['qa-superadmin@example.com', true],
            'Datamation Management' => ['qa-management@example.com', true],
            'Company Admin' => ['qa-companyadmin@example.com', false],
            'Company Supervisor' => ['qa-supervisor@example.com', false],
            'Stock Inventory User' => ['qa-stock@example.com', false],
            'Document Tracking User' => ['qa-document@example.com', false],
            'Viewer' => ['qa-viewer@example.com', false],
        ];

        foreach ($roleUsers as $role => [$email, $isPlatform]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'QA '.$role,
                    'password' => bcrypt('password'),
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'is_platform_user' => $isPlatform,
                ]
            );

            $user->syncRoles([$role]);
        }
    }
}
