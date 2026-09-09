<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\DocumentFile;
use App\Models\License;
use App\Models\Location;
use App\Models\Module;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\DocumentMovementService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds the accounts and starting records needed to run the 5-scenario
 * client demo script (registering files into a new box, transferring a
 * file, dispatching a file, transferring a box, adding a new location) —
 * a dedicated tenant/dataset, isolated from DatabaseSeeder's 'DEMO'
 * customer so demo runs never collide with other seeded data.
 *
 * A barcode scanned during the live demo that ISN'T pre-seeded here
 * naturally resolves to "unknown" (ScannerService::resolve() only matches
 * rows already in barcode_registry) — so scenarios 1 and 5's "scan
 * something new" steps need no pre-registered placeholder; any not-yet-used
 * barcode string works. See docs/DEMO_SCENARIO_DATA.md for the exact
 * strings a presenter should use.
 *
 * Idempotent and safe to re-run: every write is gated by firstOrCreate()/
 * wasRecentlyCreated so a presenter's in-demo actions (e.g. dispatching a
 * seeded file) survive a re-run.
 *
 * Refused on production; requires DMIMS_DEMO_PASSWORD (>=12 characters) on
 * every other environment, since this seeds real login credentials.
 *
 *   DMIMS_DEMO_PASSWORD='...' php artisan db:seed --class=DemoScenariosSeeder
 */
class DemoScenariosSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (App::environment('production')) {
            $this->command?->error('DemoScenariosSeeder must never run on production.');

            return;
        }

        $password = env('DMIMS_DEMO_PASSWORD');
        if (! $password || strlen($password) < 12) {
            throw new RuntimeException('DMIMS_DEMO_PASSWORD must be set (min 12 characters) to run DemoScenariosSeeder.');
        }

        DB::transaction(function () use ($password) {
            $this->call(RolesAndPermissionsSeeder::class);

            $customer = Customer::firstOrCreate(
                ['company_code' => 'DEMOSCEN'],
                [
                    'company_name' => 'Demo Scenarios Co',
                    'contact_person' => 'Demo Presenter',
                    'email' => 'demo@demo.dmims.test',
                    'status' => 'active',
                ]
            );

            $plan = SubscriptionPlan::firstOrCreate(
                ['plan_code' => 'demo-scenarios'],
                [
                    'plan_name' => 'Demo Scenarios',
                    'description' => 'Plan backing the client demo tenant',
                    'price' => 0,
                    'billing_cycle' => 'monthly',
                    'status' => 'active',
                ]
            );

            $moduleCodes = ['stock_inventory', 'document_tracking', 'barcode_scanning', 'barcode_printing'];
            foreach ($moduleCodes as $code) {
                $module = Module::firstOrCreate(['module_code' => $code], ['module_name' => $code, 'status' => 'active']);
                CustomerModule::firstOrCreate(
                    ['customer_id' => $customer->id, 'module_id' => $module->id],
                    ['is_enabled' => true, 'enabled_at' => now()]
                );
            }

            CustomerSubscription::firstOrCreate(
                ['customer_id' => $customer->id, 'subscription_plan_id' => $plan->id],
                [
                    'subscription_no' => 'SUB-DEMOSCEN-0001',
                    'valid_from' => now(),
                    'valid_to' => now()->addYear(),
                    'grace_period_days' => 7,
                    'max_users' => 10,
                    'max_document_files' => 100,
                    'max_boxes' => 50,
                    'enabled_modules' => $moduleCodes,
                    'support_level' => 'standard',
                    'status' => 'active',
                ]
            );

            License::firstOrCreate(
                ['customer_id' => $customer->id, 'license_no' => 'LIC-DEMOSCEN-0001'],
                [
                    'valid_from' => now(),
                    'valid_to' => now()->addYear(),
                    'grace_period_days' => 7,
                    'status' => 'active',
                    'technical_access_mode' => 'full',
                ]
            );

            $operator = User::firstOrCreate(
                ['email' => 'operator@demo.dmims.test'],
                [
                    'name' => 'Demo Warehouse Operator',
                    'password' => bcrypt($password),
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'is_platform_user' => false,
                ]
            );
            $operator->syncRoles(['Document Tracking User']);

            $admin = User::firstOrCreate(
                ['email' => 'admin@demo.dmims.test'],
                [
                    'name' => 'Demo Company Admin',
                    'password' => bcrypt($password),
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'is_platform_user' => false,
                ]
            );
            $admin->syncRoles(['Company Admin']);

            $barcodes = app(BarcodeService::class);
            $movement = app(DocumentMovementService::class);

            $warehouse = Location::firstOrCreate(
                ['customer_id' => $customer->id, 'location_code' => 'DEMO-WH'],
                ['location_name' => 'Demo Warehouse', 'status' => 'active']
            );
            $barcodes->registerFor($warehouse);

            $shelfA = Location::firstOrCreate(
                ['customer_id' => $customer->id, 'location_code' => 'DEMO-SHELF-A'],
                ['location_name' => 'Shelf A', 'status' => 'active', 'parent_id' => $warehouse->id]
            );
            $barcodes->registerFor($shelfA);

            $shelfB = Location::firstOrCreate(
                ['customer_id' => $customer->id, 'location_code' => 'DEMO-SHELF-B'],
                ['location_name' => 'Shelf B', 'status' => 'active', 'parent_id' => $warehouse->id]
            );
            $barcodes->registerFor($shelfB);

            // box_barcode is unique across the whole table (not per-customer),
            // so a temporary value is used at insert time — registerFor()
            // below immediately overwrites it with a real generated barcode.
            $boxA = Box::firstOrCreate(
                ['customer_id' => $customer->id, 'box_number' => 'DEMO-BOX-A'],
                ['box_barcode' => 'PENDING-DEMO-BOX-A', 'current_location_id' => $shelfA->id, 'status' => 'active']
            );
            $barcodes->registerFor($boxA);
            if ($boxA->wasRecentlyCreated) {
                $movement->receiveInBox($boxA, $shelfA->id);
            }

            $boxB = Box::firstOrCreate(
                ['customer_id' => $customer->id, 'box_number' => 'DEMO-BOX-B'],
                ['box_barcode' => 'PENDING-DEMO-BOX-B', 'current_location_id' => $shelfB->id, 'status' => 'active']
            );
            $barcodes->registerFor($boxB);
            if ($boxB->wasRecentlyCreated) {
                $movement->receiveInBox($boxB, $shelfB->id);
            }

            $files = [
                'Lease Agreement' => 'PENDING-DEMO-FILE-1',
                'Invoice Batch' => 'PENDING-DEMO-FILE-2',
                'HR Record' => 'PENDING-DEMO-FILE-3',
            ];

            foreach ($files as $title => $placeholderBarcode) {
                $file = DocumentFile::firstOrCreate(
                    ['customer_id' => $customer->id, 'title' => $title],
                    ['file_barcode' => $placeholderBarcode, 'current_status' => 'active']
                );
                $barcodes->registerFor($file);
                if ($file->wasRecentlyCreated) {
                    $movement->receiveInFile($file, $boxA->id);
                }
            }
        });
    }
}
