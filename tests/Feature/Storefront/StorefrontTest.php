<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_categories_new_arrivals_and_featured_sellers(): void
    {
        $category = Category::factory()->create(['name' => 'Electronics']);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->get('/')->assertOk()
            ->assertSee('Electronics')
            ->assertSee($product->name)
            ->assertSee($product->store->name);
    }

    public function test_only_visible_products_are_listed_and_viewable(): void
    {
        $category = Category::factory()->create();
        $visible = Product::factory()->create(['category_id' => $category->id]);
        $pending = Product::factory()->status(ProductStatus::Pending)->create(['category_id' => $category->id]);
        $unpublished = Product::factory()->create(['category_id' => $category->id, 'is_published' => false]);
        $pendingStore = Product::factory()->create(['category_id' => $category->id, 'store_id' => Store::factory()->pending()]);
        $inactiveCategory = Product::factory()->create(['category_id' => Category::factory()->create(['is_active' => false])->id]);

        $this->get("/c/{$category->slug}")->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($pending->name)
            ->assertDontSee($unpublished->name)
            ->assertDontSee($pendingStore->name);

        $this->get($visible->url())->assertOk()->assertSee($visible->name)->assertSee($visible->defaultVariant->sku);
        $this->get("/product/{$pending->store->slug}/{$pending->slug}")->assertNotFound();
        $this->get("/product/{$unpublished->store->slug}/{$unpublished->slug}")->assertNotFound();
        $this->get("/product/{$pendingStore->store->slug}/{$pendingStore->slug}")->assertNotFound();
        $this->get("/product/{$inactiveCategory->store->slug}/{$inactiveCategory->slug}")->assertNotFound();
        $this->get("/store/{$pendingStore->store->slug}")->assertNotFound();
    }

    public function test_main_category_includes_sub_categories_and_filters_work(): void
    {
        $parent = Category::factory()->create(['name' => 'Electronics']);
        $audio = Category::factory()->create(['name' => 'Audio', 'parent_id' => $parent->id]);
        $store = Store::factory()->create();

        $cheap = Product::factory()->priced(50000)->create(['category_id' => $audio->id, 'store_id' => $store->id, 'name' => 'Cheap Speaker']);
        $pricey = Product::factory()->priced(250000)->create(['category_id' => $parent->id, 'name' => 'Pricey Camera']);
        $soldOut = Product::factory()->priced(90000)->stocked(0)->create(['category_id' => $audio->id, 'name' => 'Sold Out Earbuds']);

        $this->get("/c/{$parent->slug}")->assertSee('Cheap Speaker')->assertSee('Pricey Camera')->assertSee('Audio');
        $this->get("/c/{$parent->slug}?sub={$audio->slug}")->assertSee('Cheap Speaker')->assertDontSee('Pricey Camera');
        $this->get("/c/{$parent->slug}?min=500&max=2000")->assertSee('Cheap Speaker')->assertSee('Sold Out Earbuds')->assertDontSee('Pricey Camera');
        $this->get("/c/{$parent->slug}?in_stock=1")->assertDontSee('Sold Out Earbuds');
        $this->get("/c/{$parent->slug}?seller={$store->slug}")->assertSee('Cheap Speaker')->assertDontSee('Pricey Camera');
        $this->get("/c/{$parent->slug}?sort=price_asc")->assertSeeInOrder(['Cheap Speaker', 'Sold Out Earbuds', 'Pricey Camera']);
        $this->get("/c/{$parent->slug}?sort=price_desc")->assertSeeInOrder(['Pricey Camera', 'Sold Out Earbuds', 'Cheap Speaker']);
    }

    public function test_store_page_and_sitemap(): void
    {
        $product = Product::factory()->create();

        $this->get("/store/{$product->store->slug}")->assertOk()->assertSee($product->store->name)->assertSee($product->name);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee($product->url(), false);
    }

    public function test_static_pages_render(): void
    {
        $this->get('/terms')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/refund-policy')->assertOk();
    }
}
