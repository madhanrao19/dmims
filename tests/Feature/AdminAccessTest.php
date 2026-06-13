<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_accessible(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_guest_sees_admin_login_page_when_accessing_admin_dashboard(): void
    {
        $response = $this->followingRedirects()->get('/admin');

        $response->assertStatus(200);
        $response->assertSee('Login');
    }

    public function test_platform_user_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'is_platform_user' => true,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertStatus(200);
    }
}
