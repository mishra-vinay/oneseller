<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SubOrderStatus;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends SellerController
{
    public function __invoke(Request $request): View
    {
        $store = $this->currentStore($request);
        $live = fn () => $store->subOrders()->whereNotIn('status', [SubOrderStatus::PendingPayment, SubOrderStatus::Cancelled]);

        return view('seller.dashboard', [
            'store' => $store,
            'ordersToday' => $live()->where('created_at', '>=', today())->count(),
            'revenueThisMonth' => (int) $live()->where('created_at', '>=', now()->startOfMonth())->sum('items_subtotal'),
            'awaitingAction' => $store->subOrders()->whereIn('status', SubOrderStatus::awaitingSeller())->count(),
            'balance' => $store->balance(),
            'lowStock' => ProductVariant::whereHas('product', fn ($q) => $q->where('store_id', $store->id))
                ->where('stock', '<=', ProductVariant::LOW_STOCK_THRESHOLD)
                ->with('product')
                ->orderBy('stock')
                ->limit(10)
                ->get(),
            'recentOrders' => $store->subOrders()->whereIn('status', SubOrderStatus::awaitingSeller())->with('items')->oldest()->limit(5)->get(),
        ]);
    }
}
