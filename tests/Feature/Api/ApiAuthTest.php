<?php

namespace Tests\Feature\Api;

use App\Enums\AccountStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase, ResetsApiGuards;

    public function test_buyer_registers_and_gets_a_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha Verma', 'email' => 'asha@example.com', 'phone' => '9876543210', 'password' => 'secret123', 'device_name' => 'Pixel 9',
        ])->assertCreated()->assertJsonPath('user.role', 'buyer')->assertJsonPath('user.email_verified', false);

        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'asha@example.com');
        Notification::assertSentTo(User::first(), VerifyEmail::class);
        $this->assertSame('Pixel 9', User::first()->tokens()->first()->name);
    }

    public function test_login_is_per_role(): void
    {
        $buyer = User::factory()->create();
        $seller = Store::factory()->create()->user;

        $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'password'])->assertOk()->assertJsonStructure(['token', 'user']);
        $this->postJson('/api/v1/auth/login', ['email' => $seller->email, 'password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/login', ['email' => $seller->email, 'password' => 'password', 'role' => 'seller'])->assertOk()->assertJsonPath('user.store.slug', $seller->store->slug);
        $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'password', 'role' => 'admin'])->assertUnprocessable();
    }

    public function test_tokens_only_reach_their_roles_endpoints(): void
    {
        $buyerToken = User::factory()->create()->createToken('t')->plainTextToken;
        $sellerToken = Store::factory()->create()->user->createToken('t')->plainTextToken;

        $this->withToken($buyerToken)->getJson('/api/v1/seller/dashboard')->assertForbidden();
        $this->withToken($sellerToken)->getJson('/api/v1/addresses')->assertForbidden();
        $this->withToken($sellerToken)->getJson('/api/v1/seller/dashboard')->assertOk();
        $this->withoutToken()->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken('nonsense')->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_pending_store_and_deactivated_accounts(): void
    {
        $pending = Store::factory()->pending()->create()->user;
        $this->withToken($pending->createToken('t')->plainTextToken)->getJson('/api/v1/seller/dashboard')->assertForbidden()->assertJsonPath('store_status', 'pending');

        $buyer = User::factory()->create();
        $token = $buyer->createToken('t')->plainTextToken;
        $buyer->forceFill(['status' => AccountStatus::Deactivated])->save();

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
        $this->assertSame(0, $buyer->tokens()->count(), 'the token is revoked');
        $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_profile_password_and_logout(): void
    {
        Notification::fake();
        $buyer = User::factory()->create();
        $token = $buyer->createToken('phone')->plainTextToken;
        $other = $buyer->createToken('tablet')->plainTextToken;

        $this->withToken($token)->patchJson('/api/v1/me', ['name' => 'New Name', 'email' => 'new@example.com'])
            ->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.email_verified', false);

        $this->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'newpass123'])->assertUnprocessable();
        $this->putJson('/api/v1/me/password', ['current_password' => 'password', 'password' => 'newpass123'])->assertOk();
        $this->assertSame(1, $buyer->tokens()->count(), 'other devices are signed out');

        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $buyer->tokens()->count());
    }

    public function test_errors_are_json(): void
    {
        $this->get('/api/v1/products/999999')->assertNotFound()->assertJsonStructure(['message']);
        $this->post('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
    }
}
