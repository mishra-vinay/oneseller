<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ProductStatus;
use App\Http\Requests\SellerProductInput;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        return SellerProductInput::validate($request, $product);
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
