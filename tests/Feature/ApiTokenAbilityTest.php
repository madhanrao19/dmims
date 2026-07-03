<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerSubscription;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocationStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenAbilityTest extends TestCase
{
    use RefreshDatabase;

    private function tenantSetup(): array
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        CustomerSubscription::create([
            'customer_id' => $customer->id,
            'subscription_no' => 'SUB-'.$customer->id,
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYear(),
            'status' => 'active',
        ]);
        $user = User::factory()->create(['customer_id' => $customer->id, 'is_platform_user' => false, 'status' => 'active']);
        $product = Product::create(['customer_id' => $customer->id, 'sku' => 'SKU1', 'product_name' => 'Widget', 'status' => 'active']);
        $location = Location::create(['customer_id' => $customer->id, 'location_code' => 'WH-A', 'location_name' => 'Warehouse A', 'status' => 'active']);
        ProductLocationStock::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
            'reserved_quantity' => 0,
            'available_quantity' => 10,
        ]);

        return [$user, $product];
    }

    public function test_token_without_required_ability_is_rejected(): void
    {
        [$user, $product] = $this->tenantSetup();
        $token = $user->createToken('t', ['some:other-ability'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}/stock")
            ->assertStatus(403);
    }

    public function test_token_with_api_read_ability_succeeds(): void
    {
        [$user, $product] = $this->tenantSetup();
        $token = $user->createToken('t', ['api:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}/stock")
            ->assertOk();
    }

    /** Tokens issued before this change default to Sanctum's own ['*'] ability
     *  and must keep working — this is the explicit backward-compat guard. */
    public function test_token_with_wildcard_ability_succeeds(): void
    {
        [$user, $product] = $this->tenantSetup();
        $token = $user->createToken('t')->plainTextToken; // no abilities arg => ['*']

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}/stock")
            ->assertOk();
    }

    public function test_issue_api_token_command_defaults_to_api_read_ability_and_expiration(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        Artisan::call('dmims:issue-api-token', ['user' => $user->id]);

        $token = PersonalAccessToken::where('tokenable_id', $user->id)->latest('id')->first();

        $this->assertNotNull($token);
        $this->assertSame(['api:read'], $token->abilities);
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->isFuture());
    }

    public function test_issue_api_token_command_accepts_custom_abilities(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        Artisan::call('dmims:issue-api-token', ['user' => $user->id, '--ability' => ['api:read', 'api:write']]);

        $token = PersonalAccessToken::where('tokenable_id', $user->id)->latest('id')->first();

        $this->assertSame(['api:read', 'api:write'], $token->abilities);
    }
}
