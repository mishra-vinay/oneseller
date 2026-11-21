<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use App\Services\Catalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Catalog $catalog): View
    {
        return view('home', [
            'categories' => Category::topLevel()->active()->ordered()
                ->with(['children' => fn ($q) => $q->active()->ordered()])
                ->get(),
            'newArrivals' => $catalog->base()->orderByDesc('products.approved_at')->limit(10)->get(),
            // Deals: in-stock products with the biggest saving on their listed MRP.
            'deals' => $catalog->base()
                ->whereColumn('dv.compare_at_price', '>', 'dv.price')
                ->where('dv.stock', '>', 0)
                ->orderByRaw('(dv.compare_at_price - dv.price) * 1.0 / dv.compare_at_price desc')
                ->limit(6)
                ->get(),
            // Featured sellers: the live stores with the most products on sale.
            'featuredStores' => Store::live()
                ->whereHas('products', fn ($q) => $q->visible())
                ->withCount(['products as visible_products_count' => fn ($q) => $q->visible()])
                ->orderByDesc('visible_products_count')
                ->limit(4)
                ->get(),
        ]);
    }
}
