<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\ProductModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductModeration $moderation) {}

    public function index(Request $request): View
    {
        // Defaults to the approval queue; ?status=all shows everything.
        $statusParam = $request->query('status', ProductStatus::Pending->value);
        $status = ProductStatus::tryFrom((string) $statusParam);

        $products = Product::query()
            ->with(['store', 'category', 'defaultVariant', 'images'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('store'), fn ($q, $slug) => $q->whereHas('store', fn ($s) => $s->where('slug', $slug)))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($statusParam === 'disabled', fn ($q) => $q->where('admin_disabled', true))
            ->orderBy('updated_at', $status === ProductStatus::Pending ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'statusParam' => $statusParam,
            'stores' => Store::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function show(Product $product): View
    {
        return view('admin.products.show', ['product' => $product->load(['store', 'category.parent', 'defaultVariant', 'images'])]);
    }

    public function approve(Product $product): RedirectResponse
    {
        $this->moderation->approve($product);

        return back()->with('status', __('Approved: :name', ['name' => $product->name]));
    }

    public function reject(Request $request, Product $product): RedirectResponse
    {
        $this->moderation->reject($product, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return back()->with('status', __('Rejected: :name', ['name' => $product->name]));
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $count = $this->moderation->approveMany($ids);

        return back()->with('status', trans_choice('{1} 1 product approved.|[2,*] :count products approved.', $count, ['count' => $count]));
    }

    public function disable(Product $product): RedirectResponse
    {
        $this->moderation->setDisabled($product, true);

        return back()->with('status', __('Product deactivated. It is hidden from the storefront.'));
    }

    public function enable(Product $product): RedirectResponse
    {
        $this->moderation->setDisabled($product, false);

        return back()->with('status', __('Product reactivated.'));
    }
}
