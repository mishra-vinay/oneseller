<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorePageController extends Controller
{
    public function __invoke(Request $request, Store $store, Catalog $catalog): View
    {
        abort_unless(Store::live()->whereKey($store->id)->exists(), 404);

        return view('storefront.store', [
            'store' => $store,
            'products' => $catalog->paginate($catalog->base()->where('products.store_id', $store->id), $request),
        ]);
    }
}
