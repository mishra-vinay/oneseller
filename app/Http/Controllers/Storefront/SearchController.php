<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, Catalog $catalog): View
    {
        $term = mb_substr(trim((string) $request->query('q')), 0, 100);
        $query = $catalog->search($catalog->base(), $term);

        return view('storefront.listing', [
            'title' => $term === '' ? __('All products') : __('Results for “:term”', ['term' => $term]),
            'term' => $term,
            'stores' => $catalog->storesIn($query),
            'products' => $catalog->paginate($query, $request),
        ]);
    }
}
