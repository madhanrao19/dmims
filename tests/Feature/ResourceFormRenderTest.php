<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceFormRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every resource create page must render without error for a platform
     * admin. This exercises all form components (including the migrated Select
     * fields) and catches invalid component method calls at render time.
     */
    public function test_all_resource_create_pages_render(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['is_platform_user' => true, 'status' => 'active']);
        // Platform users no longer bypass permissions for writes; a platform
        // admin is the Super Admin role (all permissions).
        $admin->assignRole('Datamation Super Admin');
        $this->actingAs($admin);

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $failures = [];
        $checked = 0;

        foreach ($panel->getResources() as $resource) {
            if (! array_key_exists('create', $resource::getPages())) {
                continue;
            }

            $url = $resource::getUrl('create');
            $status = $this->get($url)->getStatusCode();
            $checked++;

            if ($status !== 200) {
                $failures[] = class_basename($resource)." ({$url}) returned {$status}";
            }
        }

        $this->assertGreaterThan(20, $checked, 'Expected to render most resources');
        $this->assertSame([], $failures, "Resource create pages failed to render:\n".implode("\n", $failures));
    }
}
