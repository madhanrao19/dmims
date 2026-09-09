<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Location;
use App\Models\User;
use App\Services\DocumentMovementService;
use Database\Seeders\DemoScenariosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoScenariosSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('DMIMS_DEMO_PASSWORD=a-strong-demo-password');
    }

    protected function tearDown(): void
    {
        putenv('DMIMS_DEMO_PASSWORD');
        parent::tearDown();
    }

    public function test_seeder_refuses_on_production(): void
    {
        app()->instance('env', 'production');

        (new DemoScenariosSeeder)->run();

        $this->assertDatabaseMissing('customers', ['company_code' => 'DEMOSCEN']);
    }

    public function test_seeder_requires_dmims_demo_password(): void
    {
        putenv('DMIMS_DEMO_PASSWORD');

        $this->expectException(RuntimeException::class);

        (new DemoScenariosSeeder)->run();
    }

    public function test_seeder_creates_expected_counts(): void
    {
        (new DemoScenariosSeeder)->run();

        $customer = Customer::where('company_code', 'DEMOSCEN')->firstOrFail();

        $this->assertSame(2, User::where('customer_id', $customer->id)->count());
        $this->assertSame(3, Location::where('customer_id', $customer->id)->count());
        $this->assertSame(2, Box::where('customer_id', $customer->id)->count());
        $this->assertSame(3, DocumentFile::where('customer_id', $customer->id)->count());

        $operator = User::where('email', 'operator@demo.dmims.test')->firstOrFail();
        $this->assertTrue($operator->hasRole('Document Tracking User'));
        $this->assertTrue(Hash::check('a-strong-demo-password', $operator->password));

        $admin = User::where('email', 'admin@demo.dmims.test')->firstOrFail();
        $this->assertTrue($admin->hasRole('Company Admin'));
    }

    public function test_rerunning_after_a_presenter_dispatches_a_file_preserves_that_change(): void
    {
        (new DemoScenariosSeeder)->run();

        $customer = Customer::where('company_code', 'DEMOSCEN')->firstOrFail();
        $file = DocumentFile::where('customer_id', $customer->id)->firstOrFail();
        app(DocumentMovementService::class)->moveOutFile($file, 'Client office');
        $file->refresh();
        $this->assertSame('moved_out', $file->current_status);

        $originalOperatorHash = User::where('email', 'operator@demo.dmims.test')->firstOrFail()->password;

        putenv('DMIMS_DEMO_PASSWORD=a-different-second-run-password');
        (new DemoScenariosSeeder)->run();

        $file->refresh();
        $this->assertSame('moved_out', $file->current_status, 'a re-run must not reset a presenter\'s in-demo action');
        $this->assertSame(2, User::where('customer_id', $customer->id)->count());
        $this->assertSame(3, Location::where('customer_id', $customer->id)->count());
        $this->assertSame(2, Box::where('customer_id', $customer->id)->count());
        $this->assertSame(3, DocumentFile::where('customer_id', $customer->id)->count());
        $this->assertSame(
            $originalOperatorHash,
            User::where('email', 'operator@demo.dmims.test')->firstOrFail()->password,
            'a re-run must not re-hash an existing user\'s password'
        );
    }
}
