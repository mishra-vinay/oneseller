<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\Catalog;
use Illuminate\View\View;

class ProductPageController extends Controller
{
    public function __invoke(Store $store, string $product, Catalog $catalog): View
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
            'moreFromStore' => $catalog->base()
                ->where('products.store_id', $store->id)
                ->where('products.id', '!=', $model->id)
                ->orderByDesc('products.approved_at')
                ->limit(4)
                ->get(),
        ]);
    }
}
