<?php

namespace Tests\Feature\Seller;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Orders\OrderTestHelpers;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        $this->product = Product::factory()->priced(49900)->stocked(7)->create(['store_id' => $this->store->id, 'name' => 'Cotton T-Shirt']);
        $this->product->update(['short_description' => 'Soft', 'description' => 'Pure cotton']);
    }

    private function form(array $overrides = []): array
    {
        $v = $this->product->defaultVariant;

        return [
            'name' => $this->product->name,
            'category_id' => $this->product->category_id,
            'short_description' => 'Soft',
            'description' => 'Pure cotton',
            'is_published' => '1',
            'sku' => $v->sku,
            'price' => '499',
            'stock' => '7',
            'weight_grams' => '500',
            ...$overrides,
        ];
    }

    private function addSizeAndColour(): void
    {
        $this->actingAs($this->store->user)->put("/seller/products/{$this->product->id}", $this->form([
            'options' => [['name' => 'Size', 'values' => 'S, M, L'], ['name' => 'Colour', 'values' => 'Red, Blue']],
        ]))->assertSessionHasNoErrors();
    }

    public function test_adding_options_creates_every_combination_and_keeps_existing_stock(): void
    {
        $originalId = $this->product->defaultVariant->id;

        $this->addSizeAndColour();

        $product = $this->product->fresh('variants');
        $this->assertSame(['Size', 'Colour'], $product->option_names);
        $this->assertCount(6, $product->variants);
        $this->assertSame(['S / Red', 'S / Blue', 'M / Red', 'M / Blue', 'L / Red', 'L / Blue'], $product->variants->map->label()->all());

        $first = $product->variants->first();
        $this->assertSame($originalId, $first->id, 'the single variant becomes the first combination');
        $this->assertSame(7, $first->stock);
        $this->assertSame(0, $product->variants->last()->stock, 'new combinations start with no stock');
        $this->assertSame(49900, $product->variants->last()->price);
        $this->assertSame(ProductStatus::Pending, $product->status, 'new buyer-visible variants need review');

        $this->get("/seller/products/{$product->id}/edit")->assertOk()->assertSee('M / Blue')->assertSee('Variants');
    }

    public function test_variant_rows_set_price_and_stock_and_only_price_changes_need_review(): void
    {
        $this->addSizeAndColour();
        $this->product->refresh()->forceFill(['status' => ProductStatus::Approved])->save();
        $variants = $this->product->fresh('variants')->variants;

        $rows = $variants->mapWithKeys(fn ($v) => [$v->id => ['sku' => $v->sku, 'price' => '499', 'stock' => '3', 'weight_grams' => '500']])->all();
        $options = [['name' => 'Size', 'values' => 'S, M, L'], ['name' => 'Colour', 'values' => 'Red, Blue']];

        $this->put("/seller/products/{$this->product->id}", [...$this->form(), 'options' => $options, 'variants' => $rows]);
        $this->assertSame(ProductStatus::Approved, $this->product->fresh()->status, 'stock-only changes stay live');
        $this->assertSame(18, $this->product->fresh()->totalStock());

        $rows[$variants->last()->id]['price'] = '599';
        $this->put("/seller/products/{$this->product->id}", [...$this->form(), 'options' => $options, 'variants' => $rows]);
        $this->assertSame(ProductStatus::Pending, $this->product->fresh()->status);
        $this->assertSame(59900, $variants->last()->fresh()->price);
    }

    public function test_cheapest_variant_becomes_the_default(): void
    {
        $this->addSizeAndColour();
        $variants = $this->product->fresh('variants')->variants;
        $rows = $variants->mapWithKeys(fn ($v, $i) => [$v->id => ['sku' => $v->sku, 'price' => (string) (500 + $i * 10), 'stock' => '1']])->all();
        $rows[$variants[3]->id]['price'] = '450';

        $this->put("/seller/products/{$this->product->id}", [...$this->form(), 'options' => [['name' => 'Size', 'values' => 'S, M, L'], ['name' => 'Colour', 'values' => 'Red, Blue']], 'variants' => $rows]);

        $this->assertSame($variants[3]->id, $this->product->fresh()->defaultVariant->id);
        $this->assertSame(1, $this->product->variants()->where('is_default', true)->count());
    }

    public function test_removing_values_and_options(): void
    {
        $this->addSizeAndColour();

        $this->put("/seller/products/{$this->product->id}", [...$this->form(), 'options' => [['name' => 'Size', 'values' => 'S, M'], ['name' => 'Colour', 'values' => 'Red, Blue']]]);
        $this->assertCount(4, $this->product->fresh('variants')->variants);

        $default = $this->product->fresh()->defaultVariant;
        $this->put("/seller/products/{$this->product->id}", $this->form(['options' => [['name' => '', 'values' => '']]]));

        $product = $this->product->fresh('variants');
        $this->assertNull($product->option_names);
        $this->assertCount(1, $product->variants);
        $this->assertNull($product->variants->first()->options);
        $this->assertSame($default->id, $product->variants->first()->id);
    }

    public function test_combination_limit(): void
    {
        $this->actingAs($this->store->user)->put("/seller/products/{$this->product->id}", $this->form([
            'options' => [['name' => 'Size', 'values' => implode(',', range(1, 10))], ['name' => 'Colour', 'values' => implode(',', range(1, 6))]],
        ]))->assertSessionHasErrors('options');
    }

    public function test_new_product_with_options_gets_the_form_stock_on_every_variant(): void
    {
        $this->actingAs($this->store->user)->post('/seller/products', [
            ...$this->form(['name' => 'Kurta', 'sku' => 'KURTA', 'stock' => '4']),
            'options' => [['name' => 'Size', 'values' => 'M, L']],
            'action' => 'submit',
        ])->assertSessionHasNoErrors();

        $kurta = Product::firstWhere('name', 'Kurta');
        $this->assertSame(['KURTA-M', 'KURTA-L'], $kurta->variants->pluck('sku')->all());
        $this->assertSame([4, 4], $kurta->variants->pluck('stock')->all());
    }

    public function test_buyer_picks_a_variant_and_it_flows_into_cart_and_order(): void
    {
        $this->addSizeAndColour();
        $this->product->refresh()->forceFill(['status' => ProductStatus::Approved])->save();
        $variants = $this->product->fresh('variants')->variants;
        $mBlue = $variants->first(fn ($v) => $v->label() === 'M / Blue');
        $mBlue->update(['stock' => 2, 'price' => 54900]);

        $this->get($this->product->url())->assertOk()
            ->assertSee('Size / Colour')
            ->assertSee('M / Blue')
            ->assertSee('sold out')
            ->assertSee('From');

        $order = $this->checkout($this->buyerWithAddress(), [[$this->product, 1]]);
        $this->assertSame('S / Red', $order->subOrders->first()->items->first()->variant_label, 'the default (first in stock) variant');

        $buyer = $order->buyer;
        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => $mBlue->id, 'quantity' => 5]);
        $this->assertSame(2, Cart::firstWhere('user_id', $buyer->id)->items()->first()->quantity, 'capped at that variant\'s stock');
        $this->get('/cart')->assertSee('M / Blue');

        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);
        $this->post('/checkout/place', ['payment_method' => 'cod']);
        $item = $buyer->orders()->latest('id')->first()->subOrders->first()->items->first();
        $this->assertSame('M / Blue', $item->variant_label);
        $this->assertSame(54900, $item->unit_price);
        $this->assertSame(0, $mBlue->fresh()->stock);

        $this->get('/order/'.$item->subOrder->order->number)->assertSee('Cotton T-Shirt (M / Blue)');
    }

    public function test_in_stock_filter_counts_any_variant(): void
    {
        $this->addSizeAndColour();
        $this->product->refresh()->forceFill(['status' => ProductStatus::Approved])->save();
        $this->product->variants()->update(['stock' => 0]);
        $category = Category::find($this->product->category_id);

        $this->get("/c/{$category->slug}?in_stock=1")->assertDontSee('Cotton T-Shirt');

        $this->product->variants()->orderByDesc('id')->first()->update(['stock' => 1]);
        $this->get("/c/{$category->slug}?in_stock=1")->assertSee('Cotton T-Shirt');
    }

    public function test_inventory_lists_each_variant(): void
    {
        $this->addSizeAndColour();

        $this->get('/seller/inventory')->assertOk()->assertSee('L / Blue');
    }
}
