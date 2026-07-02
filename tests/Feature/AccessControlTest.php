<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\License;
use App\Models\Module;
use App\Models\User;
use App\Services\AccessControlService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AccessControlService::flushCache();
    }

    private function makeCustomerUser(?string $mode, string $status = 'active'): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        $module = Module::create(['module_code' => 'stock_inventory', 'module_name' => 'Stock', 'status' => 'active']);
        CustomerModule::create([
            'customer_id' => $customer->id,
            'module_id' => $module->id,
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);

        if ($mode !== null) {
            License::create([
                'customer_id' => $customer->id,
                'license_no' => 'LIC-'.$customer->id,
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
                'status' => $status,
                'technical_access_mode' => $mode,
            ]);
        }

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Stock Inventory User'); // grants "manage inventory"

        return $user;
    }

    public function test_full_license_allows_read_and_write(): void
    {
        $user = $this->makeCustomerUser('full');
        $this->actingAs($user);

        $this->assertTrue(ProductResource::can('viewAny'));
        $this->assertTrue(ProductResource::can('create'));
        $this->assertTrue(ProductResource::can('update'));
    }

    public function test_view_only_license_allows_read_but_blocks_write(): void
    {
        $user = $this->makeCustomerUser('view_only');
        $this->actingAs($user);

        $this->assertTrue(ProductResource::can('viewAny'));
        $this->assertFalse(ProductResource::can('create'));
        $this->assertFalse(ProductResource::can('update'));
        $this->assertFalse(ProductResource::can('delete'));
    }

    public function test_blocked_license_blocks_everything(): void
    {
        $user = $this->makeCustomerUser('blocked');
        $access = app(AccessControlService::class);

        $this->assertSame(AccessControlService::MODE_BLOCKED, $access->getEffectiveAccessMode($user->customer_id));
        $this->assertFalse($access->canLogin($user));
        $this->assertFalse($access->canPerformOperationalAction($user));
        $this->assertFalse($access->canView($user));
    }

    public function test_missing_license_defaults_to_full_access(): void
    {
        $user = $this->makeCustomerUser(null);
        $access = app(AccessControlService::class);

        $this->assertSame(AccessControlService::MODE_FULL, $access->getEffectiveAccessMode($user->customer_id));
        $this->assertTrue($access->canPerformOperationalAction($user));
    }

    public function test_platform_user_is_never_restricted_by_license(): void
    {
        $access = app(AccessControlService::class);
        $platform = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);

        $this->assertTrue($access->canPerformOperationalAction($platform));
        $this->assertTrue($access->canLogin($platform));
    }

    /** A license whose validity has lapsed must not keep full access just because
     *  a scheduled job never flipped its `status` column to expired. */
    public function test_expired_license_degrades_to_view_only_even_if_status_still_active(): void
    {
        $customer = Customer::create(['company_name' => 'Lapsed', 'company_code' => 'LAP', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-LAP',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(),   // lapsed yesterday
            'grace_period_days' => 0,
            'status' => 'active',            // status never updated
            'technical_access_mode' => 'full',
        ]);

        $access = app(AccessControlService::class);
        AccessControlService::flushCache();

        $this->assertSame(AccessControlService::MODE_VIEW_ONLY, $access->getEffectiveAccessMode($customer->id));
    }

    public function test_license_valid_through_today_keeps_full_access(): void
    {
        $customer = Customer::create(['company_name' => 'Current', 'company_code' => 'CUR', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-CUR',
            'valid_from' => now()->subYear(),
            'valid_to' => now(),             // valid through end of today
            'grace_period_days' => 0,
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        $access = app(AccessControlService::class);
        AccessControlService::flushCache();

        $this->assertSame(AccessControlService::MODE_FULL, $access->getEffectiveAccessMode($customer->id));
    }

    public function test_expired_license_within_grace_period_keeps_full_access(): void
    {
        $customer = Customer::create(['company_name' => 'Grace', 'company_code' => 'GRC', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-GRC',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->subDay(),   // lapsed yesterday
            'grace_period_days' => 7,        // but within a 7-day grace window
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        $access = app(AccessControlService::class);
        AccessControlService::flushCache();

        $this->assertSame(AccessControlService::MODE_FULL, $access->getEffectiveAccessMode($customer->id));
    }
}
