<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase, ResetsApiGuards;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privatePem);
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        config(['services.fcm.credentials_json' => base64_encode(json_encode([
            'project_id' => 'oneseller-test',
            'client_email' => 'push@oneseller-test.iam.gserviceaccount.com',
            'private_key' => $privatePem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]))]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => function (Request $request) {
                return $request['message']['token'] === 'dead-token'
                    ? Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                    : Http::response(['name' => 'projects/oneseller-test/messages/1']);
            },
        ]);
    }

    public function test_devices_register_and_unregister(): void
    {
        $buyer = User::factory()->create();
        $this->withToken($buyer->createToken('t')->plainTextToken);

        $this->postJson('/api/v1/devices', ['token' => 'fcm-abc', 'platform' => 'android'])->assertCreated();
        $this->postJson('/api/v1/devices', ['token' => 'fcm-abc', 'platform' => 'android'])->assertCreated();
        $this->assertSame(1, $buyer->deviceTokens()->count());
        $this->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'windows'])->assertJsonValidationErrors('platform');

        $this->deleteJson('/api/v1/devices/fcm-abc')->assertNoContent();
        $this->assertSame(0, $buyer->deviceTokens()->count());
    }

    public function test_marketplace_events_are_pushed_to_the_buyers_devices(): void
    {
        $product = Product::factory()->create();
        $order = $this->checkout($this->buyerWithAddress(), [[$product, 1]]);
        $buyer = $order->buyer;
        $buyer->deviceTokens()->createMany([['token' => 'good-token', 'platform' => 'ios'], ['token' => 'dead-token', 'platform' => 'android']]);
        $sub = $order->subOrders->first();

        $this->actingAs($product->store->user);
        $this->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'Delhivery', 'tracking_number' => 'DL1']);

        Http::assertSent(function (Request $request) use ($sub) {
            if (! str_contains($request->url(), 'fcm.googleapis.com/v1/projects/oneseller-test/messages:send') || $request['message']['token'] !== 'good-token') {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer ya29.test')
                && $request['message']['notification']['title'] === 'OneSeller'
                && str_starts_with($request['message']['notification']['body'], 'Shipped: ')
                && $request['message']['data']['type'] === 'SubOrderShipped'
                && str_contains($request['message']['data']['url'], $sub->order->number);
        });

        // The service-account JWT is properly signed.
        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return false;
            }

            [$header, $claims, $signature] = explode('.', $request['assertion']);
            $decoded = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && $decoded['scope'] === 'https://www.googleapis.com/auth/firebase.messaging'
                && openssl_verify("{$header}.{$claims}", base64_decode(strtr($signature, '-_', '+/')), $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
        });

        $this->assertNull(DeviceToken::firstWhere('token', 'dead-token'), 'unregistered tokens are removed');
        $this->assertNotNull(DeviceToken::firstWhere('token', 'good-token')->last_used_at);
    }

    public function test_no_push_without_credentials_or_devices(): void
    {
        $product = Product::factory()->create();
        $order = $this->checkout($this->buyerWithAddress(), [[$product, 1]]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'));

        config(['services.fcm.credentials_json' => null]);
        $order->buyer->deviceTokens()->create(['token' => 'good-token', 'platform' => 'ios']);
        $sub = $order->subOrders->first();
        $this->actingAs($product->store->user)->post("/seller/orders/{$sub->id}/accept");
        $this->post("/seller/orders/{$sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T']);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com'));
    }
}
