<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __invoke(Request $request, Category $category, Catalog $catalog): View
    {
        abort_unless(Category::active()->whereKey($category->id)->exists(), 404);

        $category->load(['parent', 'children' => fn ($q) => $q->where('is_active', true)]);

        // A main category shows its own and all its sub-categories' products; ?sub= narrows to one.
        $sub = $request->query('sub') ? $category->children->firstWhere('slug', $request->query('sub')) : null;
        $categoryIds = $sub ? [$sub->id] : [$category->id, ...$category->children->pluck('id')];

        $query = $catalog->base()->whereIn('products.category_id', $categoryIds);

        return view('storefront.listing', [
            'title' => $category->name,
            'category' => $category,
            'activeSub' => $sub,
            'stores' => $catalog->storesIn($query),
            'products' => $catalog->paginate($query, $request),
        ]);
    }
}
