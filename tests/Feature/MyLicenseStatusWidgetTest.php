<?php

namespace Tests\Feature;

use App\Filament\Widgets\MyLicenseStatusWidget;
use App\Models\Customer;
use App\Models\License;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyLicenseStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_is_hidden_from_platform_users(): void
    {
        $this->actingAs(User::factory()->create(['is_platform_user' => true, 'status' => 'active']));

        $this->assertFalse(MyLicenseStatusWidget::canView());
    }

    public function test_widget_is_visible_to_customer_users(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $this->actingAs($user);

        $this->assertTrue(MyLicenseStatusWidget::canView());
    }

    public function test_widget_reports_no_license_when_none_exists(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $this->actingAs($user);

        $widget = new MyLicenseStatusWidget;
        $stats = $this->callGetStats($widget);

        $this->assertSame('No License', $stats[0]->getValue());
    }

    public function test_widget_reports_own_license_status_only(): void
    {
        $customerA = Customer::create(['company_name' => 'Alpha', 'company_code' => 'A', 'status' => 'active']);
        $customerB = Customer::create(['company_name' => 'Beta', 'company_code' => 'B', 'status' => 'active']);

        License::create([
            'customer_id' => $customerA->id,
            'license_no' => 'LIC-A',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);
        License::create([
            'customer_id' => $customerB->id,
            'license_no' => 'LIC-B',
            'valid_from' => now()->subYear(),
            'valid_to' => now()->addYear(),
            'status' => 'suspended',
            'technical_access_mode' => 'blocked',
        ]);

        $user = User::factory()->create(['customer_id' => $customerA->id, 'is_platform_user' => false, 'status' => 'active']);
        $this->actingAs($user);

        $widget = new MyLicenseStatusWidget;
        $stats = $this->callGetStats($widget);

        $this->assertSame('Active', $stats[0]->getValue());
    }

    /** @return array<int, Stat> */
    private function callGetStats(MyLicenseStatusWidget $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }
}
