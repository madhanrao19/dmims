<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Customer;
use App\Models\License;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test: the UserResource form (available to any user holding
 * "manage users", including the tenant-scoped Company Admin role) exposed an
 * unrestricted `is_platform_user` toggle and an unscoped `roles` Select. A
 * Company Admin could self-escalate a user to platform-wide access by
 * toggling is_platform_user and/or assigning "Datamation Super Admin" /
 * "Datamation Management". UserResource::stripDisallowedRoles() is the
 * server-side guarantee (called from CreateUser::afterCreate /
 * EditUser::afterSave regardless of what the client submitted).
 */
class UserResourcePrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function companyAdmin(): User
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        $user = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Company Admin');

        return $user;
    }

    private function companySupervisor(int $customerId): User
    {
        $user = User::factory()->create([
            'customer_id' => $customerId,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $user->assignRole('Company Supervisor');

        return $user;
    }

    public function test_non_platform_actor_cannot_grant_a_platform_role(): void
    {
        $this->actingAs($this->companyAdmin());

        $target = User::factory()->create(['is_platform_user' => false, 'status' => 'active']);
        $target->assignRole('Datamation Super Admin');

        UserResource::stripDisallowedRoles($target);

        $this->assertFalse($target->fresh()->hasRole('Datamation Super Admin'));
    }

    public function test_platform_actor_may_keep_a_platform_role(): void
    {
        $platformAdmin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $this->actingAs($platformAdmin);

        $target = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $target->assignRole('Datamation Management');

        UserResource::stripDisallowedRoles($target);

        $this->assertTrue($target->fresh()->hasRole('Datamation Management'));
    }

    public function test_non_platform_actor_cannot_set_is_platform_user_via_create(): void
    {
        $this->actingAs($this->companyAdmin());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Escalated User',
                'email' => 'escalated@example.com',
                'password' => 'password123',
                'status' => 'active',
                'is_platform_user' => true,
            ])
            ->call('create');

        $created = User::where('email', 'escalated@example.com')->firstOrFail();
        $this->assertFalse($created->is_platform_user);
    }

    public function test_non_platform_actor_cannot_write_to_a_same_tenant_platform_user(): void
    {
        // A platform user can share a tenant's customer_id — DatabaseSeeder's
        // own admin@example.com does exactly this. BaseResource's tenant
        // scope alone would let a same-tenant Company Admin open that
        // record's edit page and set a new password (full platform
        // takeover) without ever touching is_platform_user or roles.
        $customer = Customer::create(['company_name' => 'Shared Co', 'company_code' => 'SHR', 'status' => 'active']);

        $platformUser = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => true,
            'status' => 'active',
        ]);

        $companyAdmin = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
        ]);
        $companyAdmin->assignRole('Company Admin');
        $this->actingAs($companyAdmin);

        $this->assertFalse(UserResource::can('update', $platformUser));
        $this->assertFalse(UserResource::can('delete', $platformUser));
        // Reads (list/view) are unaffected — only writes are denied.
        $this->assertTrue(UserResource::can('view', $platformUser));
    }

    /** Security & Access Control Matrix §6: "Update User: Limited" for
     *  Company Supervisor — may reach the update action (unlike Create/
     *  Delete, both denied) and edit operational fields, but identity/
     *  security/privilege fields must stay locked even if a crafted
     *  request submits different values for them. */
    public function test_company_supervisor_has_limited_update_access(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        License::create([
            'customer_id' => $customer->id,
            'license_no' => 'LIC-'.$customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);
        $supervisor = $this->companySupervisor($customer->id);

        $target = User::factory()->create([
            'customer_id' => $customer->id,
            'is_platform_user' => false,
            'status' => 'active',
            'email' => 'original@example.com',
            'username' => 'original',
        ]);
        $target->assignRole('Viewer');

        $this->actingAs($supervisor);

        // Create and delete stay denied — only update is reachable.
        $this->assertFalse(UserResource::can('create'));
        $this->assertFalse(UserResource::can('delete', $target));
        $this->assertTrue(UserResource::can('update', $target));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'phone' => '555-0100',
                'job_title' => 'Lead Clerk',
                'email' => 'escalated@example.com',
                'username' => 'escalated',
                'status' => 'suspended',
                'roles' => [Role::findByName('Company Admin')->id],
            ])
            ->call('save');

        $target->refresh();

        // Operational fields: allowed through.
        $this->assertSame('Updated Name', $target->name);
        $this->assertSame('555-0100', $target->phone);
        $this->assertSame('Lead Clerk', $target->job_title);

        // Identity/security/privilege fields: unchanged despite submission.
        $this->assertSame('original@example.com', $target->email);
        $this->assertSame('original', $target->username);
        $this->assertSame('active', $target->status);
        $this->assertTrue($target->hasRole('Viewer'));
        $this->assertFalse($target->hasRole('Company Admin'));
    }
}
