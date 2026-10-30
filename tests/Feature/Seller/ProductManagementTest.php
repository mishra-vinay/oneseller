<?php

namespace Tests\Feature\Seller;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ProductReviewed;
use App\Notifications\ProductSubmitted;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->store = Store::factory()->create();
        $this->category = Category::factory()->create();
    }

    private function form(array $overrides = []): array
    {
        return [
            'name' => 'JBL Go 3 Speaker',
            'category_id' => $this->category->id,
            'price' => '2799',
            'compare_at_price' => '3299',
            'stock' => '25',
            'sku' => 'JBL-GO3',
            'weight_grams' => '300',
            'short_description' => 'Pocket speaker',
            'description' => 'Waterproof Bluetooth speaker.',
            'is_published' => '1',
            ...$overrides,
        ];
    }

    public function test_seller_saves_a_draft_then_submits_it(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($this->store->user)->get('/seller/products/create')->assertOk();
        $this->post('/seller/products', $this->form(['action' => 'draft', 'images' => [UploadedFile::fake()->image('a.jpg', 2000, 1000)]]))->assertRedirect();

        $product = Product::with('defaultVariant', 'images')->firstOrFail();
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertSame('jbl-go-3-speaker', $product->slug);
        $this->assertSame(279900, $product->defaultVariant->price);
        $this->assertSame(329900, $product->defaultVariant->compare_at_price);
        $this->assertCount(1, $product->images);
        Storage::disk('public')->assertExists($product->images->first()->path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($product->images->first()->path));
        $this->assertSame(1600, $width);
        Notification::assertNothingSent();

        $this->put("/seller/products/{$product->id}", $this->form(['action' => 'submit']));
        $this->assertSame(ProductStatus::Pending, $product->fresh()->status);
        Notification::assertSentTo($admin, ProductSubmitted::class);
    }

    public function test_editing_live_content_sends_it_back_for_review_but_stock_does_not(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id, 'category_id' => $this->category->id, 'name' => 'JBL Go 3 Speaker']);
        $product->defaultVariant->update(['price' => 279900, 'compare_at_price' => 329900, 'stock' => 25, 'sku' => 'JBL-GO3', 'weight_grams' => 300]);
        $product->update(['short_description' => 'Pocket speaker', 'description' => 'Waterproof Bluetooth speaker.']);

        $this->actingAs($this->store->user);

        $this->put("/seller/products/{$product->id}", $this->form(['stock' => '5', 'is_published' => '0']));
        $this->assertSame(ProductStatus::Approved, $product->fresh()->status, 'stock and publish changes keep it live');
        $this->assertSame(5, $product->defaultVariant->fresh()->stock);

        $this->put("/seller/products/{$product->id}", $this->form(['stock' => '5', 'price' => '2599']));
        $this->assertSame(ProductStatus::Pending, $product->fresh()->status, 'a price change needs review');
    }

    public function test_rejected_product_shows_reason_and_can_be_resubmitted(): void
    {
        Notification::fake();
        $product = Product::factory()->status(ProductStatus::Pending)->create(['store_id' => $this->store->id, 'category_id' => $this->category->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/admin/products/{$product->id}/reject", ['reason' => 'Blurry photos']);
        $this->assertSame(ProductStatus::Rejected, $product->fresh()->status);
        Notification::assertSentTo($this->store->user, ProductReviewed::class);

        $this->actingAs($this->store->user)->get("/seller/products/{$product->id}/edit")->assertSee('Blurry photos');
        $this->put("/seller/products/{$product->id}", $this->form(['action' => 'submit']));
        $this->assertSame(ProductStatus::Pending, $product->fresh()->status);
        $this->assertNull($product->fresh()->rejection_reason);
    }

    public function test_image_limit_is_five(): void
    {
        $this->actingAs($this->store->user)->post('/seller/products', $this->form([
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("{$i}.jpg"), range(1, 6)),
        ]))->assertSessionHasErrors('images');
    }

    public function test_auto_approve_products(): void
    {
        app(Settings::class)->set(['auto_approve_products' => true]);

        $this->actingAs($this->store->user)->post('/seller/products', $this->form(['action' => 'submit']));

        $this->assertSame(ProductStatus::Approved, Product::first()->status);
    }

    public function test_admin_queue_bulk_approve_and_force_deactivate(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = Product::factory()->count(3)->status(ProductStatus::Pending)->create(['store_id' => $this->store->id]);
        $live = Product::factory()->create(['store_id' => $this->store->id]);

        $this->actingAs($admin)->get('/admin/products')->assertOk()->assertSee($pending[0]->name)->assertDontSee($live->name);
        $this->get("/admin/products/{$pending[0]->id}")->assertOk();

        $this->post('/admin/products/bulk-approve', ['ids' => $pending->pluck('id')->all()])->assertSessionHas('status', '3 products approved.');
        $this->assertSame(0, Product::where('status', ProductStatus::Pending)->count());

        $this->post("/admin/products/{$live->id}/disable");
        $this->get($live->url())->assertNotFound();
        $this->post("/admin/products/{$live->id}/enable");
        $this->get($live->url())->assertOk();
    }

    public function test_inventory_table_updates_stock_instantly_and_sends_price_changes_for_review(): void
    {
        $a = Product::factory()->create(['store_id' => $this->store->id]);
        $b = Product::factory()->create(['store_id' => $this->store->id]);

        $this->actingAs($this->store->user)->get('/seller/inventory')->assertOk()->assertSee($a->name);

        $this->put('/seller/inventory', ['rows' => [
            $a->id => ['price' => '499.00', 'stock' => '3'],
            $b->id => ['price' => '550', 'stock' => '10'],
        ]])->assertSessionHas('status', '2 products updated. 1 price change was sent for approval.');

        $this->assertSame(3, $a->defaultVariant->fresh()->stock);
        $this->assertSame(ProductStatus::Approved, $a->fresh()->status);
        $this->assertSame(55000, $b->defaultVariant->fresh()->price);
        $this->assertSame(ProductStatus::Pending, $b->fresh()->status);
    }

    public function test_seller_can_delete_a_product(): void
    {
        $product = Product::factory()->create(['store_id' => $this->store->id]);

        $this->actingAs($this->store->user)->delete("/seller/products/{$product->id}")->assertRedirect('/seller/products');

        $this->assertModelMissing($product);
    }
}
