<?php

namespace Tests\Feature;

use App\Filament\Resources\TagResource;
use App\Filament\Resources\TagResource\Pages\CreateTag;
use App\Filament\Resources\TagResource\Pages\EditTag;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Models\Customer;
use App\Models\CustomerModule;
use App\Models\License;
use App\Models\Module;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filament-resource-level coverage for the new standalone Tag module
 * (feature-enhancement request: tags managed as their own resource, not
 * only via the inline createOptionForm on Box/Document File). Model-level
 * relation/uniqueness coverage already lives in TagsTest.php.
 */
class TagResourceTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
    }

    private function platformAdmin(): User
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        return $admin;
    }

    private function tenantUser(string $role): User
    {
        License::create([
            'customer_id' => $this->customer->id,
            'license_no' => 'LIC-'.$this->customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
            'technical_access_mode' => 'full',
        ]);

        $module = Module::firstOrCreate(['module_code' => 'document_tracking'], ['module_name' => 'document_tracking', 'status' => 'active']);
        CustomerModule::create(['customer_id' => $this->customer->id, 'module_id' => $module->id, 'is_enabled' => true, 'enabled_at' => now()]);

        $user = User::factory()->create(['customer_id' => $this->customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_a_tenant_user_can_create_edit_and_delete_a_tag(): void
    {
        $this->tenantUser('Document Tracking User');

        Livewire::test(CreateTag::class)
            ->fillForm(['name' => 'Urgent', 'color' => '#ff0000'])
            ->call('create')
            ->assertHasNoFormErrors();

        $tag = Tag::where('name', 'Urgent')->firstOrFail();
        $this->assertSame($this->customer->id, $tag->customer_id);

        Livewire::test(EditTag::class, ['record' => $tag->id])
            ->fillForm(['name' => 'Critical'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Critical', $tag->fresh()->name);

        $tag->delete();
        $this->assertModelMissing($tag);
    }

    public function test_a_tenant_user_cannot_see_another_customers_tags(): void
    {
        $otherCustomer = Customer::create(['company_name' => 'Globex', 'company_code' => 'GLX', 'status' => 'active']);
        Tag::create(['customer_id' => $otherCustomer->id, 'name' => 'Other Tenant Tag']);

        $this->tenantUser('Document Tracking User');
        Tag::create(['customer_id' => $this->customer->id, 'name' => 'My Tag']);

        Livewire::test(ListTags::class)
            ->assertCanSeeTableRecords(Tag::where('customer_id', $this->customer->id)->get())
            ->assertCanNotSeeTableRecords(Tag::where('customer_id', $otherCustomer->id)->get());
    }

    public function test_viewer_gets_403_on_tag_create_and_edit(): void
    {
        // Viewer holds 'view documents' but not 'manage documents' — the
        // same permission Box/Document File resources gate their writes on.
        $this->tenantUser('Viewer');
        $tag = Tag::create(['customer_id' => $this->customer->id, 'name' => 'Existing']);

        $this->get(TagResource::getUrl('create'))->assertForbidden();
        $this->get(TagResource::getUrl('edit', ['record' => $tag]))->assertForbidden();
    }
}
