<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\Catalog;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductPageController extends Controller
{
    public function __invoke(Request $request, Store $store, string $product, Catalog $catalog, ReviewService $reviews): View
    {
        $model = Product::visible()
            ->where('store_id', $store->id)
            ->where('slug', $product)
            ->with(['images', 'defaultVariant', 'variants', 'category.parent', 'store'])
            ->firstOrFail();

        return view('storefront.product', [
            'product' => $model,
            // Preselect the cheapest variant that is in stock, else the default.
            'variant' => $model->variants->where('stock', '>', 0)->sortBy('price')->first() ?? $model->defaultVariant,
            'reviews' => $model->reviews()->shown()->with('user')->latest()->paginate(10, pageName: 'reviews'),
            'canReview' => $reviews->canReview($request->user(), $model),
            'myReview' => $request->user() ? $model->reviews()->where('user_id', $request->user()->id)->first() : null,
            'wishlisted' => (bool) $request->user()?->hasWishlisted($model),
            'moreFromStore' => $catalog->base()
                ->where('products.store_id', $store->id)
                ->where('products.id', '!=', $model->id)
                ->orderByDesc('products.approved_at')
                ->limit(4)
                ->get(),
        ]);
    }
}
