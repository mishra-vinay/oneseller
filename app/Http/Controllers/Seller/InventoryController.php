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
 * One table of every variant with price and stock editable in place, saved together.
 */
class InventoryController extends SellerController
{
    public function index(Request $request): View
    {
        $variants = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.store_id', $this->currentStore($request)->id)
            ->select('product_variants.*')
            ->with('product')
            ->when($request->boolean('low'), fn ($q) => $q->where('product_variants.stock', '<=', ProductVariant::LOW_STOCK_THRESHOLD))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('products.name', 'like', "%{$term}%")->orWhere('product_variants.sku', 'like', "%{$term}%")))
            ->orderBy('products.name')
            ->orderBy('product_variants.position')
            ->paginate(100)
            ->withQueryString();

        return view('seller.inventory', ['variants' => $variants]);
    }

    public function update(Request $request, ProductService $service): RedirectResponse
    {
        $rows = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.price' => ['required', Rules::RUPEES],
            'rows.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
        ], ['rows.*.price.regex' => __('Enter prices in rupees, e.g. 499 or 499.50.')])['rows'];

        // Only this store's variants: ids from other stores are silently ignored.
        $variants = ProductVariant::with('product')
            ->whereIn('id', array_keys($rows))
            ->whereHas('product', fn ($q) => $q->where('store_id', $this->currentStore($request)->id))
            ->get();

        $changed = 0;
        $sentForReview = 0;

        foreach ($variants as $variant) {
            $price = Money::fromRupees($rows[$variant->id]['price']);
            $stock = (int) $rows[$variant->id]['stock'];

            if ($variant->price === $price && $variant->stock === $stock) {
                continue;
            }

            $before = $variant->product->status;
            $service->updateInventory($variant, $price, $stock);
            $changed++;
            $sentForReview += (int) ($before !== $variant->product->status);
        }

        $message = trans_choice('{0} Nothing changed.|{1} 1 item updated.|[2,*] :count items updated.', $changed, ['count' => $changed]);

        if ($sentForReview > 0) {
            $message .= ' '.trans_choice('{1} 1 price change was sent for approval.|[2,*] :count price changes were sent for approval.', $sentForReview, ['count' => $sentForReview]);
        }

        return back()->with('status', $message);
    }
}
