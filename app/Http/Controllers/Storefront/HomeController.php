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
            'categories' => Category::topLevel()->active()->ordered()->get(),
            'newArrivals' => $catalog->base()->orderByDesc('products.approved_at')->limit(8)->get(),
            // Featured sellers: the live stores with the most products on sale.
            'featuredStores' => Store::live()
                ->whereHas('products', fn ($q) => $q->visible())
                ->withCount(['products as visible_products_count' => fn ($q) => $q->visible()])
                ->orderByDesc('visible_products_count')
                ->limit(6)
                ->get(),
        ]);
    }
}
