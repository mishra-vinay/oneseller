<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.wishlist', [
            // Saved items stay listed; ones no longer on sale are shown as unavailable.
            'products' => $request->user()->wishlist()->with(['store', 'images', 'defaultVariant', 'variants'])
                ->orderByPivot('created_at', 'desc')->paginate(24),
            'visibleIds' => Product::visible()->whereIn('products.id', $request->user()->wishlist()->pluck('products.id'))->pluck('products.id')->flip(),
        ]);
    }

    public function toggle(Request $request, Product $product): RedirectResponse
    {
        $wishlist = $request->user()->wishlist();

        if ($wishlist->whereKey($product->id)->exists()) {
            $request->user()->wishlist()->detach($product->id);

            return back()->with('status', __('Removed from your wishlist.'));
        }

        abort_unless($product->isVisible(), 404);
        $request->user()->wishlist()->attach($product->id, ['created_at' => now()]);

        return back()->with('status', __('Saved to your wishlist.'));
    }
}
