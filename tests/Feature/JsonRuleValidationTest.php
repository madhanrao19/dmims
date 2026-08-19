<?php

namespace Tests\Feature;

use App\Filament\Resources\LicenseResource\Pages\CreateLicense;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression for BaseResource::jsonRule(): ->rule(static::jsonRule()) threw
 * a 500 (BindingResolutionException: "[$attribute] was unresolvable") on
 * every submit, because Filament's evaluate() dependency-injects any raw
 * closure passed to ->rule() by parameter name, and had no way to treat a
 * (string $attribute, mixed $value, Closure $fail) closure as a plain
 * Laravel validation callback instead. Found while creating a License
 * through the actual admin UI.
 */
class JsonRuleValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_creating_a_license_with_invalid_json_fails_validation_instead_of_500(): void
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        Livewire::test(CreateLicense::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'license_no' => 'LIC-1',
                'deployment_mode' => 'DatamationOnPremHosted',
                'license_mode' => 'InternalSubscription',
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
                'enabled_modules' => 'not valid json',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['enabled_modules']);
    }

    public function test_creating_a_license_with_valid_json_succeeds(): void
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        Livewire::test(CreateLicense::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'license_no' => 'LIC-2',
                'deployment_mode' => 'DatamationOnPremHosted',
                'license_mode' => 'InternalSubscription',
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
                'enabled_modules' => '["stock_inventory"]',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('licenses', ['license_no' => 'LIC-2']);
    }
}
