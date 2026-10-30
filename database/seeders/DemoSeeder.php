<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\Role;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A small working marketplace for local testing. All accounts use the password "password1".
 *
 *   admin@example.com            /admin/login
 *   ravi@example.com             /seller/login   (Ravi Electronics, approved)
 *   meera@example.com            /seller/login   (Meera Handlooms, approved)
 *   kiran@example.com            /seller/login   (Kiran Home, pending approval)
 *   buyer@example.com            /login
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password1';

    public function run(): void
    {
        $this->user(Role::Admin, 'Marketplace Admin', 'admin@example.com');
        $this->user(Role::Buyer, 'Asha Verma', 'buyer@example.com');

        $categories = $this->categories();

        $ravi = $this->store('Ravi Electronics', 'ravi@example.com', 'Ravi Kumar', 'Bengaluru', 'Karnataka', StoreStatus::Approved);
        $meera = $this->store('Meera Handlooms', 'meera@example.com', 'Meera Iyer', 'Chennai', 'Tamil Nadu', StoreStatus::Approved);
        $kiran = $this->store('Kiran Home', 'kiran@example.com', 'Kiran Shah', 'Ahmedabad', 'Gujarat', StoreStatus::Pending);

        $this->product($ravi, $categories['audio'], 'JBL Go 3 Speaker', 279900, 329900, 25, '#1d4ed8');
        $this->product($ravi, $categories['audio'], 'Boat Rockerz 450 Headphones', 149900, 299000, 40, '#0f172a');
        $this->product($ravi, $categories['mobile-accessories'], 'Fast Charger 33W', 89900, null, 3, '#16a34a');
        $this->product($ravi, $categories['mobile-accessories'], 'USB-C Cable 1.5m', 29900, 49900, 120, '#475569');
        $this->product($ravi, $categories['audio'], 'Wireless Earbuds Pro', 199900, null, 0, '#7c3aed');
        $this->product($ravi, $categories['mobile-accessories'], 'Power Bank 20000mAh', 179900, 249900, 15, '#ea580c', ProductStatus::Pending);

        $this->product($meera, $categories['sarees'], 'Kanchipuram Silk Saree', 1249900, 1499900, 6, '#be123c');
        $this->product($meera, $categories['sarees'], 'Cotton Handloom Saree', 249900, null, 18, '#0d9488');
        $this->product($meera, $categories['home-textiles'], 'Handwoven Cotton Bedsheet', 129900, 159900, 30, '#ca8a04');
        $this->product($meera, $categories['home-textiles'], 'Block Print Cushion Covers (Set of 5)', 79900, null, 4, '#9333ea');

        $this->product($kiran, $categories['home-textiles'], 'Jute Floor Rug', 99900, null, 10, '#a16207', ProductStatus::Pending);
    }

    private function user(Role $role, string $name, string $email): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'phone' => '98'.random_int(10000000, 99999999), 'password' => self::PASSWORD]);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * @return array<string, Category>
     */
    private function categories(): array
    {
        $tree = [
            'Electronics' => ['Audio', 'Mobile Accessories'],
            'Fashion' => ['Sarees'],
            'Home' => ['Home Textiles'],
        ];

        $bySlug = [];
        $order = 0;

        foreach ($tree as $parentName => $children) {
            $parent = Category::updateOrCreate(['slug' => Str::slug($parentName)], ['name' => $parentName, 'sort_order' => $order++, 'is_active' => true]);
            $parent->image_path ??= $this->image("categories/{$parent->slug}.jpg", $parentName, '#44403c');
            $parent->save();

            foreach ($children as $i => $childName) {
                $bySlug[Str::slug($childName)] = Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    ['name' => $childName, 'parent_id' => $parent->id, 'sort_order' => $i, 'is_active' => true],
                );
            }
        }

        return $bySlug;
    }

    private function store(string $name, string $email, string $owner, string $city, string $state, StoreStatus $status): Store
    {
        $user = $this->user(Role::Seller, $owner, $email);

        $store = Store::firstOrNew(['user_id' => $user->id]);
        $store->fill([
            'name' => $name,
            'description' => "Welcome to {$name}. Genuine products, shipped from {$city}.",
            'phone' => $user->phone,
            'address_line1' => '12 Market Road',
            'city' => $city,
            'state' => $state,
            'pincode' => '560001',
        ]);
        $store->slug ??= Str::slug($name);
        $store->status = $status;
        $store->approved_at = $status === StoreStatus::Approved ? now() : null;
        $store->save();

        return $store;
    }

    private function product(Store $store, Category $category, string $name, int $price, ?int $compareAt, int $stock, string $color, ProductStatus $status = ProductStatus::Approved): void
    {
        $product = Product::firstOrNew(['store_id' => $store->id, 'slug' => Str::slug($name)]);
        $product->fill([
            'category_id' => $category->id,
            'name' => $name,
            'short_description' => "Quality {$category->name} from {$store->name}.",
            'description' => "{$name} sold by {$store->name}.\n\nShips within 2 working days. Prices include GST.",
            'is_published' => true,
        ]);
        $product->status = $status;
        $product->approved_at = $status === ProductStatus::Approved ? now()->subMinutes(random_int(1, 5000)) : null;
        $product->save();

        $variant = $product->defaultVariant ?? new ProductVariant;
        $variant->fill(['sku' => strtoupper(Str::substr(Str::slug($name, ''), 0, 8)).'-'.$product->id, 'price' => $price, 'compare_at_price' => $compareAt, 'stock' => $stock, 'weight_grams' => 500]);
        $variant->is_default = true;
        $product->variants()->save($variant);

        if ($product->images()->doesntExist()) {
            $product->images()->create(['path' => $this->image("products/{$product->id}/demo.jpg", $name, $color), 'sort_order' => 0]);
        }
    }

    /**
     * A plain coloured placeholder image with the product name, so the storefront has something to show.
     */
    private function image(string $path, string $label, string $hex): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $path;
        }

        $img = imagecreatetruecolor(800, 800);
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));
        $white = imagecolorallocate($img, 255, 255, 255);

        foreach (array_slice(explode("\n", wordwrap($label, 22)), 0, 4) as $i => $line) {
            $x = (int) max(10, (800 - strlen($line) * imagefontwidth(5) * 2) / 2);
            $tile = imagecreatetruecolor(strlen($line) * imagefontwidth(5), imagefontheight(5));
            imagefill($tile, 0, 0, imagecolorallocate($tile, $r, $g, $b));
            imagestring($tile, 5, 0, 0, $line, imagecolorallocate($tile, 255, 255, 255));
            imagecopyresized($img, $tile, $x, 330 + $i * 40, 0, 0, imagesx($tile) * 2, imagesy($tile) * 2, imagesx($tile), imagesy($tile));
        }

        imagerectangle($img, 20, 20, 779, 779, $white);

        ob_start();
        imagejpeg($img, null, 85);
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }
}
