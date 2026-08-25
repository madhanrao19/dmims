<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerSubscriptionResource\Pages\CreateCustomerSubscription;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\CustomerSubscription;
use App\Models\Module;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * App\Observers\CustomerSubscriptionObserver::syncEnabledModules().
 *
 * Found during a full QA pass on Herd (25 Aug 2026): a blank enabled_modules
 * field used to mean "disable every CustomerModule row for this customer"
 * (whereNotIn('module_id', []) matches everything) — no form warned about
 * this, and Customer 360's "Add Subscription" action made the field a much
 * more casual, frequent touch point. Fixed so blank means "leave existing
 * module access untouched"; a non-blank list still syncs exactly as before.
 */
class CustomerSubscriptionModuleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function customerWithEnabledModule(string $moduleCode = 'stock_inventory'): Customer
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $module = Module::firstOrCreate(['module_code' => $moduleCode], ['module_name' => $moduleCode, 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        return $customer;
    }

    public function test_blank_enabled_modules_on_create_does_not_disable_existing_modules(): void
    {
        $customer = $this->customerWithEnabledModule();

        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-1',
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
            // enabled_modules deliberately omitted.
        ]);

        $this->assertDatabaseHas('customer_modules', [
            'customer_id' => $customer->id,
            'is_enabled' => true,
        ]);
    }

    public function test_blank_enabled_modules_on_update_does_not_disable_existing_modules(): void
    {
        $customer = $this->customerWithEnabledModule();

        $subscription = CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-1',
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
            'enabled_modules' => ['stock_inventory'],
        ]);

        // Update something unrelated, leaving enabled_modules blank.
        $subscription->update(['support_level' => 'premium']);

        $this->assertDatabaseHas('customer_modules', [
            'customer_id' => $customer->id,
            'is_enabled' => true,
        ]);
    }

    /**
     * Found during a second full QA pass on Herd (25 Aug 2026): submitting
     * the Textarea's raw JSON string straight to the model was double-
     * encoded by Eloquent's array cast setter, so it was read back as the
     * original string rather than an array — syncEnabledModules()'s
     * whereIn() then hit a hard TypeError (count(): Argument #1 ($value)
     * must be of type Countable|array, string given), a live 500 on every
     * create/edit with a non-blank Enabled Modules field. Reproduces the
     * actual form-submission path (a plain JSON string, the same as what a
     * user types into the Textarea), not just an array passed directly to
     * the model as the other tests in this file do.
     */
    public function test_submitting_the_create_form_with_a_json_string_does_not_crash_and_stores_a_real_array(): void
    {
        $customer = $this->customerWithEnabledModule();
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        Livewire::test(CreateCustomerSubscription::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'subscription_no' => 'SUB-JSON',
                'valid_from' => now()->toDateString(),
                'valid_to' => now()->addYear()->toDateString(),
                'status' => 'trial',
                'enabled_modules' => '["stock_inventory"]',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $subscription = CustomerSubscription::where('subscription_no', 'SUB-JSON')->firstOrFail();
        $this->assertIsArray($subscription->enabled_modules);
        $this->assertSame(['stock_inventory'], $subscription->enabled_modules);
    }

    public function test_a_non_blank_list_still_syncs_exactly_enabling_listed_and_disabling_the_rest(): void
    {
        $customer = $this->customerWithEnabledModule('stock_inventory');
        $otherModule = Module::firstOrCreate(['module_code' => 'document_tracking'], ['module_name' => 'Document Tracking', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $customer->id, 'module_id' => $otherModule->id, 'is_enabled' => true, 'enabled_at' => now()]);

        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-2',
            'valid_from' => now(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
            'enabled_modules' => ['stock_inventory'],
        ]);

        $this->assertDatabaseHas('customer_modules', [
            'customer_id' => $customer->id,
            'module_id' => Module::where('module_code', 'stock_inventory')->value('id'),
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('customer_modules', [
            'customer_id' => $customer->id,
            'module_id' => $otherModule->id,
            'is_enabled' => false,
        ]);
    }
}
