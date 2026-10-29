<?php

namespace App\Http\Controllers\Seller;

use App\Models\ProductVariant;
use App\Services\ProductService;
use App\Support\Money;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One table of every product with price and stock editable in place, saved together.
 */
class InventoryController extends SellerController
{
    public function index(Request $request): View
    {
        $products = $this->currentStore($request)->products()
            ->join('product_variants as dv', fn ($j) => $j->on('dv.product_id', '=', 'products.id')->where('dv.is_default', true))
            ->select('products.*')
            ->with('defaultVariant')
            ->when($request->boolean('low'), fn ($q) => $q->where('dv.stock', '<=', ProductVariant::LOW_STOCK_THRESHOLD))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('products.name', 'like', "%{$term}%")->orWhere('dv.sku', 'like', "%{$term}%")))
            ->orderBy('products.name')
            ->paginate(100)
            ->withQueryString();

        return view('seller.inventory', ['products' => $products]);
    }

    public function update(Request $request, ProductService $service): RedirectResponse
    {
        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.price' => ['required', Rules::RUPEES],
            'rows.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], ['rows.*.price.regex' => __('Enter prices in rupees, e.g. 499 or 499.50.')])['rows'];

        $products = $this->currentStore($request)->products()->with('defaultVariant')->whereIn('id', array_keys($rows))->get();
        $changed = 0;
        $sentForReview = 0;

        foreach ($products as $product) {
            $price = Money::fromRupees($rows[$product->id]['price']);
            $stock = (int) $rows[$product->id]['stock'];
            $variant = $product->defaultVariant;

            if ($variant->price === $price && $variant->stock === $stock) {
                continue;
            }

            $before = $product->status;
            $service->updateInventory($product, $price, $stock);
            $changed++;
            $sentForReview += (int) ($before !== $product->status);
        }

        $message = trans_choice('{0} Nothing changed.|{1} 1 product updated.|[2,*] :count products updated.', $changed, ['count' => $changed]);

        if ($sentForReview > 0) {
            $message .= ' '.trans_choice('{1} 1 price change was sent for approval.|[2,*] :count price changes were sent for approval.', $sentForReview, ['count' => $sentForReview]);
        }

        return back()->with('status', $message);
    }
}
