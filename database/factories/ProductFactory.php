<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates the product with its default variant (₹499, 10 in stock). Use ->priced() / ->stocked() to change them.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(3, true));

        return [
            'store_id' => Store::factory(),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => ProductStatus::Approved,
            'is_published' => true,
            'admin_disabled' => false,
            'approved_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            $variant = new ProductVariant([
                'sku' => 'SKU-'.$product->id,
                'price' => 49900,
                'stock' => 10,
                'weight_grams' => 500,
            ]);
            $variant->is_default = true;
            $product->variants()->save($variant);
        });
    }

    public function priced(int $paise): static
    {
        return $this->afterCreating(fn (Product $product) => $product->defaultVariant()->update(['price' => $paise]));
    }

    public function stocked(int $stock): static
    {
        return $this->afterCreating(fn (Product $product) => $product->defaultVariant()->update(['stock' => $stock]));
    }

    public function status(ProductStatus $status): static
    {
        return $this->state(fn () => ['status' => $status, 'approved_at' => $status === ProductStatus::Approved ? now() : null]);
    }
}
