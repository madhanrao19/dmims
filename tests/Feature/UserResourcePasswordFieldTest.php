<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for the password field added to UserResource (it was
 * entirely missing, so every "Create User" attempt threw a DB NOT NULL
 * violation). The field is dehydrated only when filled
 * (dehydrated(fn ($state) => filled($state))) so leaving it blank on edit
 * must NOT touch the stored hash — if that predicate is ever wrong, every
 * user's password silently nulls out the next time an admin edits their
 * profile.
 */
class UserResourcePasswordFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_leaving_password_blank_on_edit_preserves_the_existing_hash(): void
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $target = User::factory()->create([
            'is_platform_user' => false,
            'status' => 'active',
            'password' => Hash::make('original-password'),
        ]);
        $originalHash = $target->password;

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => 'Renamed User',
                'email' => $target->email,
                'password' => null,
                'status' => 'active',
            ])
            ->call('save');

        $this->assertSame($originalHash, $target->fresh()->password);
        $this->assertTrue(Hash::check('original-password', $target->fresh()->password));
    }

    public function test_setting_a_new_password_on_edit_updates_the_hash(): void
    {
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $target = User::factory()->create([
            'is_platform_user' => false,
            'status' => 'active',
            'password' => Hash::make('original-password'),
        ]);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm([
                'name' => $target->name,
                'email' => $target->email,
                'password' => 'brand-new-password',
                'status' => 'active',
            ])
            ->call('save');

        $this->assertTrue(Hash::check('brand-new-password', $target->fresh()->password));
    }
}
