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
            ->with(['images', 'defaultVariant', 'category.parent', 'store'])
            ->firstOrFail();

        return view('storefront.product', [
            'product' => $model,
            'variant' => $model->defaultVariant,
            'moreFromStore' => $catalog->base()
                ->where('products.store_id', $store->id)
                ->where('products.id', '!=', $model->id)
                ->orderByDesc('products.approved_at')
                ->limit(4)
                ->get(),
        ]);
    }
}
