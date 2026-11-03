<?php

namespace Tests\Feature\Api;

use App\Enums\ProductStatus;
use App\Enums\SubOrderStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class ApiSellerTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase, ResetsApiGuards;

    private Store $store;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->store = Store::factory()->create();
        $this->token = $this->store->user->createToken('seller-app', ['seller'])->plainTextToken;
    }

    private function seller()
    {
        return $this->withToken($this->token);
    }

    public function test_seller_creates_a_product_with_variants_in_paise(): void
    {
        $category = Category::factory()->create();

        $id = $this->seller()->post('/api/v1/seller/products', [
            'name' => 'Linen Shirt', 'category_id' => $category->id,
            'sku' => 'LIN', 'price' => 129900, 'compare_at_price' => 159900, 'stock' => 5,
            'options' => [['name' => 'Size', 'values' => ['M', 'L']]],
            'images' => [UploadedFile::fake()->image('shirt.jpg')],
            'submit' => '1',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.option_names', ['Size'])
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.variants.0.price', 129900)
            ->assertJsonPath('data.variants.0.label', 'M')
            ->assertJsonCount(1, 'data.images')
            ->json('data.id');

        $variants = Product::find($id)->variants;
        $this->postJson("/api/v1/seller/products/{$id}", [
            'name' => 'Linen Shirt', 'category_id' => $category->id,
            'options' => [['name' => 'Size', 'values' => 'M, L']],
            'variants' => [
                $variants[0]->id => ['sku' => 'LIN-M', 'price' => 129900, 'stock' => 8],
                $variants[1]->id => ['sku' => 'LIN-L', 'price' => 139900, 'stock' => 2],
            ],
        ])->assertOk()->assertJsonPath('data.variants.1.price', 139900)->assertJsonPath('data.variants.1.stock', 2);

        $this->getJson('/api/v1/seller/products')->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/v1/seller/products/{$id}")->assertJsonPath('data.is_published', true);
        $this->postJson('/api/v1/seller/products', ['name' => 'Bad'])->assertJsonValidationErrors(['category_id', 'price', 'sku', 'stock']);
    }

    public function test_inventory_and_isolation(): void
    {
        $mine = Product::factory()->create(['store_id' => $this->store->id]);
        $theirs = Product::factory()->create();

        $this->seller()->getJson('/api/v1/seller/inventory')->assertJsonCount(1, 'data')->assertJsonPath('data.0.product.id', $mine->id);
        $this->patchJson('/api/v1/seller/inventory', ['rows' => [
            $mine->defaultVariant->id => ['price' => 49900, 'stock' => 2],
            $theirs->defaultVariant->id => ['price' => 1, 'stock' => 0],
        ]])->assertJsonPath('updated', 1);

        $this->assertSame(2, $mine->defaultVariant->fresh()->stock);
        $this->assertSame(10, $theirs->defaultVariant->fresh()->stock);
        $this->getJson("/api/v1/seller/products/{$theirs->id}")->assertNotFound();
        $this->deleteJson("/api/v1/seller/products/{$theirs->id}")->assertNotFound();
        $this->deleteJson("/api/v1/seller/products/{$mine->id}")->assertNoContent();
    }

    public function test_fulfilment_and_earnings(): void
    {
        $product = Product::factory()->priced(100000)->create(['store_id' => $this->store->id]);
        $order = $this->checkout($this->buyerWithAddress(), [[$product, 1]]);
        $sub = $order->subOrders->first();

        // Another store's order, which this seller must not be able to touch.
        $otherBuyer = User::factory()->create(['email' => 'other@example.com']);
        $otherBuyer->addresses()->create(['name' => 'B', 'phone' => '9876543210', 'address_line1' => 'x', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001']);
        $otherSub = $this->checkout($otherBuyer, [[Product::factory()->create(), 1]])->subOrders->first();

        $this->seller()->getJson('/api/v1/seller/orders')->assertJsonPath('data.0.number', $sub->number)->assertJsonMissingPath('data.0.deliver_to.email');
        $this->getJson("/api/v1/seller/orders/{$sub->id}")->assertJsonPath('data.deliver_to.city', 'Pune')->assertJsonPath('data.net_payout', 90000);
        $this->getJson('/api/v1/seller/dashboard')->assertJsonPath('awaiting_action', 1);

        $this->postJson("/api/v1/seller/orders/{$sub->id}/ship", ['courier' => 'X', 'tracking_number' => 'T'])->assertUnprocessable();
        $this->postJson("/api/v1/seller/orders/{$sub->id}/accept")->assertJsonPath('data.status', 'accepted');
        $this->postJson("/api/v1/seller/orders/{$sub->id}/ship", ['courier' => 'Delhivery', 'tracking_number' => 'DL1'])->assertJsonPath('data.tracking_number', 'DL1');
        $this->postJson("/api/v1/seller/orders/{$sub->id}/deliver")->assertJsonPath('data.status', 'delivered');

        $this->getJson('/api/v1/seller/earnings')->assertJsonPath('balance', -10000)->assertJsonCount(3, 'data');

        $this->postJson("/api/v1/seller/orders/{$otherSub->id}/accept")->assertNotFound();
    }

    public function test_returns_reviews_and_notifications(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id]);
        $order = $this->checkout($this->buyerWithAddress(), [[$product, 1]]);
        $sub = $order->subOrders->first();
        $sub->update(['status' => SubOrderStatus::Delivered, 'delivered_at' => now()]);
        $this->post("/order/{$order->number}/parcels/{$sub->id}/return", ['items' => [$sub->items->first()->id => 1], 'type' => 'replacement', 'reason' => 'damaged']);
        $this->post("/product/{$this->store->slug}/{$product->slug}/review", ['rating' => 3, 'body' => 'OK']);
        $number = $sub->returns()->first()->number;

        $this->seller()->getJson('/api/v1/seller/returns')->assertJsonPath('data.0.number', $number);
        $this->postJson("/api/v1/seller/returns/{$number}/approve")->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/v1/seller/returns/{$number}/receive", ['restock' => false])->assertJsonPath('data.status', 'received');
        $this->postJson("/api/v1/seller/returns/{$number}/replace", ['courier' => 'X', 'tracking_number' => 'R1'])->assertJsonPath('data.status', 'replaced');

        $reviewId = Review::first()->id;
        $this->getJson('/api/v1/seller/reviews')->assertJsonPath('data.0.body', 'OK');
        $this->postJson("/api/v1/seller/reviews/{$reviewId}/reply", ['reply' => 'Thanks!'])->assertJsonPath('data.seller_reply', 'Thanks!');

        $this->getJson('/api/v1/seller/notifications')->assertOk()->assertJsonPath('data.0.read', false);
        $this->postJson('/api/v1/seller/notifications/read')->assertOk();
        $this->getJson('/api/v1/seller/notifications')->assertJsonPath('data.0.read', true);
    }

    public function test_live_product_price_change_goes_for_review(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id]);

        $this->seller()->patchJson('/api/v1/seller/inventory', ['rows' => [$product->defaultVariant->id => ['price' => 59900, 'stock' => 10]]]);

        $this->assertSame(ProductStatus::Pending, $product->fresh()->status);
    }
}
