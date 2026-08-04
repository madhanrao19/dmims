<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ListUnlicensedActiveCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_active_customers_missing_a_license(): void
    {
        $affected = Customer::create(['company_name' => 'No License Co', 'company_code' => 'NLC', 'status' => 'active']);
        CustomerSubscription::create([
            'customer_id' => $affected->id,
            'subscription_no' => 'SUB-NLC',
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
        ]);

        $licensed = Customer::create(['company_name' => 'Licensed Co', 'company_code' => 'LIC', 'status' => 'active']);
        CustomerSubscription::create([
            'customer_id' => $licensed->id,
            'subscription_no' => 'SUB-LIC',
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addMonth(),
            'status' => 'active',
        ]);
        License::create([
            'customer_id' => $licensed->id,
            'license_no' => 'LIC-LIC',
            'valid_from' => now()->subMonth(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        $noSubscription = Customer::create(['company_name' => 'No Sub Co', 'company_code' => 'NSC', 'status' => 'active']);

        Artisan::call('dmims:unlicensed-active-customers');
        $output = Artisan::output();

        $this->assertStringContainsString('No License Co', $output);
        $this->assertStringNotContainsString('Licensed Co', $output);
        $this->assertStringNotContainsString('No Sub Co', $output);
    }

    public function test_reports_nothing_to_do_when_all_active_customers_are_licensed(): void
    {
        Artisan::call('dmims:unlicensed-active-customers');
        $output = Artisan::output();

        $this->assertStringContainsString('Nothing to do', $output);
    }
}
