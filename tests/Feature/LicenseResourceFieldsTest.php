<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression coverage for the CONFORMANCE_GAP_ANALYSIS §22 cleanup: License
 * no longer stores max_users/max_products/max_document_files/max_boxes/
 * enabled_modules/allowed_reports — those duplicated CustomerSubscription's
 * identically-named columns and were never read by any enforcement code
 * (AccessControlService::getEffectiveLimits() reads CustomerSubscription
 * exclusively). This is a dead-field removal, not a change to the License/
 * Subscription architectural split (ADR-005 is unaffected).
 */
class LicenseResourceFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_COLUMNS = [
        'max_users',
        'max_products',
        'max_document_files',
        'max_boxes',
        'enabled_modules',
        'allowed_reports',
    ];

    public function test_the_dead_columns_no_longer_exist_on_the_licenses_table(): void
    {
        foreach (self::REMOVED_COLUMNS as $column) {
            $this->assertFalse(
                Schema::hasColumn('licenses', $column),
                "Expected 'licenses.{$column}' to have been dropped."
            );
        }
    }

    public function test_a_license_can_still_be_created_without_the_removed_fields(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);

        $license = License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-TEST-1',
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        $this->assertTrue($license->exists);
        $this->assertSame('LIC-TEST-1', $license->fresh()->license_no);
    }
}
