<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\Money;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends SellerController
{
    public function index(Request $request): View
    {
        $status = ProductStatus::tryFrom((string) $request->query('status'));

        $products = $this->currentStore($request)->products()
            ->with(['defaultVariant', 'images', 'category'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', '%'.$term.'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('seller.products.index', ['products' => $products, 'status' => $status]);
    }

    public function create(): View
    {
        return view('seller.products.form', ['product' => new Product(['is_published' => true]), 'categories' => $this->categoryOptions()]);
    }

    public function store(Request $request, ProductService $service): RedirectResponse
    {
        $product = $service->save(
            $this->currentStore($request),
            null,
            $this->validated($request),
            $request->file('images', []),
            [],
            $request->input('action') === 'submit',
        );

        return redirect()->route('seller.products.edit', $product->id)->with('status', $this->savedMessage($product));
    }

    public function edit(Request $request, int $product): View
    {
        return view('seller.products.form', [
            'product' => $this->find($request, $product)->load(['defaultVariant', 'variants', 'images']),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, int $product, ProductService $service): RedirectResponse
    {
        $model = $this->find($request, $product);

        $saved = $service->save(
            $this->currentStore($request),
            $model,
            $this->validated($request, $model),
            $request->file('images', []),
            array_map('intval', $request->input('remove_images', [])),
            $request->input('action') === 'submit',
        );

        return back()->with('status', $this->savedMessage($saved));
    }

    public function destroy(Request $request, int $product, ProductService $service): RedirectResponse
    {
        $service->delete($this->find($request, $product));

        return redirect()->route('seller.products.index')->with('status', __('Product deleted.'));
    }

    private function find(Request $request, int $id): Product
    {
        return $this->currentStore($request)->products()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $existingImages = $product ? $product->images()->whereNotIn('id', $request->input('remove_images', []))->count() : 0;
        $maxNew = Product::MAX_IMAGES - $existingImages;

        // A product with saved options is edited as a table of variant rows; otherwise flat fields.
        $flat = ! $request->has('variants');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', Rule::exists('categories', 'id')->where('is_active', true)],
            'price' => [Rule::requiredIf($flat), Rules::RUPEES],
            'compare_at_price' => ['nullable', Rules::RUPEES],
            'stock' => [Rule::requiredIf($flat), 'integer', 'min:0', 'max:1000000'],
            'sku' => [Rule::requiredIf($flat), 'string', 'max:64'],
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'options' => ['array', 'max:'.ProductService::MAX_OPTIONS],
            'options.*.name' => ['nullable', 'string', 'max:30'],
            'options.*.values' => ['nullable', 'string', 'max:300'],
            'variants' => ['array'],
            'variants.*.sku' => ['required', 'string', 'max:64'],
            'variants.*.price' => ['required', Rules::RUPEES],
            'variants.*.compare_at_price' => ['nullable', Rules::RUPEES],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'images' => ['array', 'max:'.max(0, $maxNew)],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_images' => ['array'],
            'remove_images.*' => ['integer'],
        ], [
            ...Rules::messages(),
            'variants.*.price.regex' => __('Enter prices in rupees, e.g. 499 or 499.50.'),
            'images.max' => __('A product can have at most :max images.', ['max' => Product::MAX_IMAGES]),
        ]);

        $combinations = count(ProductService::combinations(array_values(array_filter(
            array_map(fn ($o) => ['name' => trim($o['name'] ?? ''), 'values' => array_filter(array_map('trim', explode(',', $o['values'] ?? '')), 'strlen')], $data['options'] ?? []),
            fn ($o) => $o['name'] !== '' && $o['values'] !== [],
        ))));

        if ($combinations > ProductService::MAX_VARIANTS) {
            throw ValidationException::withMessages(['options' => __('That makes :count combinations; the limit is :max.', ['count' => $combinations, 'max' => ProductService::MAX_VARIANTS])]);
        }

        if ($flat) {
            [$data['price'], $data['compare_at_price']] = $this->prices($data['price'], $data['compare_at_price'] ?? null);
        } else {
            unset($data['price'], $data['compare_at_price'], $data['sku'], $data['stock']);

            foreach ($data['variants'] as $id => $row) {
                [$row['price'], $row['compare_at_price']] = $this->prices($row['price'], $row['compare_at_price'] ?? null);
                $row['weight_grams'] = (int) ($row['weight_grams'] ?? 0);
                $data['variants'][$id] = $row;
            }
        }

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }

    /**
     * @return array{0: int, 1: int|null} price and compare-at price in paise; compare-at is dropped unless higher
     */
    private function prices(string $price, ?string $compareAt): array
    {
        $price = Money::fromRupees($price);
        $compareAt = filled($compareAt) ? Money::fromRupees($compareAt) : null;

        return [$price, $compareAt !== null && $compareAt > $price ? $compareAt : null];
    }

    private function savedMessage(Product $product): string
    {
        return match ($product->status) {
            ProductStatus::Draft => __('Saved as draft.'),
            ProductStatus::Pending => __('Saved. The product is waiting for admin approval.'),
            ProductStatus::Approved => __('Saved. The product is live.'),
            ProductStatus::Rejected => __('Saved. Submit it again when it is ready for review.'),
        };
    }

    /**
     * @return Collection<int, Category>
     */
    private function categoryOptions()
    {
        return Category::topLevel()->where('is_active', true)->ordered()
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->get();
    }
}
