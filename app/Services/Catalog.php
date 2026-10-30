<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Storefront product listing shared by category, search and store pages.
 * Every filter lives in the query string so a filtered view can be bookmarked or shared.
 */
class Catalog
{
    public const PER_PAGE = 24;

    public const SORTS = [
        'newest' => 'Newest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
    ];

    /**
     * Visible products joined to their default variant, so price and stock can be filtered and sorted.
     *
     * @return Builder<Product>
     */
    public function base(): Builder
    {
        return Product::query()
            ->visible()
            ->join('product_variants as dv', fn ($join) => $join->on('dv.product_id', '=', 'products.id')->where('dv.is_default', true))
            ->select('products.*')
            ->with(['store', 'images', 'defaultVariant']);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function search(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        if (in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $words = collect(preg_split('/\s+/', preg_replace('/[^\pL\pN\s]+/u', ' ', $term)))
                ->filter(fn ($w) => mb_strlen($w) > 1)
                ->map(fn ($w) => '+'.$w.'*')
                ->implode(' ');

            return $words === '' ? $query : $query->whereFullText(['products.name', 'products.description'], $words, ['mode' => 'boolean']);
        }

        foreach (preg_split('/\s+/', $term) as $word) {
            $query->where(fn ($q) => $q->where('products.name', 'like', "%{$word}%")->orWhere('products.description', 'like', "%{$word}%"));
        }

        return $query;
    }

    /**
     * Applies price range (rupees), seller, in-stock and sort filters, then paginates.
     *
     * @param  Builder<Product>  $query
     */
    public function paginate(Builder $query, Request $request): LengthAwarePaginator
    {
        if (is_numeric($request->query('min'))) {
            $query->where('dv.price', '>=', (int) round($request->query('min') * 100));
        }

        if (is_numeric($request->query('max'))) {
            $query->where('dv.price', '<=', (int) round($request->query('max') * 100));
        }

        if ($request->query('seller')) {
            $query->whereHas('store', fn ($q) => $q->where('slug', $request->query('seller')));
        }

        if ($request->boolean('in_stock')) {
            $query->where('dv.stock', '>', 0);
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('dv.price')->orderByDesc('products.id'),
            'price_desc' => $query->orderByDesc('dv.price')->orderByDesc('products.id'),
            default => $query->orderByDesc('products.approved_at')->orderByDesc('products.id'),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Stores that have products in the given (unfiltered) listing, for the seller filter.
     *
     * @param  Builder<Product>  $query
     * @return Collection<int, Store>
     */
    public function storesIn(Builder $query): Collection
    {
        $storeIds = (clone $query)->reorder()->select('products.store_id')->distinct()->pluck('store_id');

        return Store::whereIn('id', $storeIds)->orderBy('name')->get(['id', 'name', 'slug']);
    }
}
