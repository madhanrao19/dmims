<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerSubscriptionResource\Pages\CreateCustomerSubscription;
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
 *
 * Originally exercised via LicenseResource's own 'enabled_modules' field;
 * repointed to CustomerSubscriptionResource (CONFORMANCE_GAP_ANALYSIS §22)
 * after License's duplicate max_users/max_products/max_document_files/
 * max_boxes/enabled_modules/allowed_reports fields were removed as dead
 * and unread — jsonRule() is a shared BaseResource helper,
 * and CustomerSubscriptionResource's 'enabled_modules'/'allowed_reports'
 * fields use the exact same ->rule(static::jsonRule()) mechanism, so this
 * keeps the regression covered without resurrecting the removed fields.
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

    public function test_creating_a_subscription_with_invalid_json_fails_validation_instead_of_500(): void
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        Livewire::test(CreateCustomerSubscription::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'subscription_no' => 'SUB-1',
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
                'enabled_modules' => 'not valid json',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasFormErrors(['enabled_modules']);
    }

    public function test_creating_a_subscription_with_valid_json_succeeds(): void
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        Livewire::test(CreateCustomerSubscription::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'subscription_no' => 'SUB-2',
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addYear(),
                'enabled_modules' => '["stock_inventory"]',
                'status' => 'active',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customer_subscriptions', ['subscription_no' => 'SUB-2']);
    }
}
