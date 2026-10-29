<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([['loc' => route('home'), 'lastmod' => null]])
            ->concat(Category::active()->get()->map(fn ($c) => ['loc' => route('category.show', $c), 'lastmod' => $c->updated_at]))
            ->concat(Store::live()->get()->map(fn ($s) => ['loc' => route('store.show', $s), 'lastmod' => $s->updated_at]))
            ->concat(Product::visible()->with('store')->lazy()->map(fn ($p) => ['loc' => $p->url(), 'lastmod' => $p->updated_at]));

        return response()
            ->view('storefront.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
