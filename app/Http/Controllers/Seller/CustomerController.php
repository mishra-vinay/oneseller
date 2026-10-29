<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SubOrderStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Buyers who ordered from this seller. Email addresses are never exposed.
 */
class CustomerController extends SellerController
{
    public function __invoke(Request $request): View
    {
        $stats = DB::table('sub_orders')
            ->join('orders', 'orders.id', '=', 'sub_orders.order_id')
            ->where('sub_orders.store_id', $this->currentStore($request)->id)
            ->whereNotIn('sub_orders.status', [SubOrderStatus::PendingPayment->value, SubOrderStatus::Cancelled->value])
            ->groupBy('orders.buyer_id')
            ->selectRaw('orders.buyer_id, count(*) as order_count, sum(sub_orders.items_subtotal + sub_orders.shipping) as total_spent, max(sub_orders.created_at) as last_order_at, max(orders.ship_city) as city')
            ->orderByDesc('last_order_at')
            ->paginate(50);

        $names = User::whereIn('id', collect($stats->items())->pluck('buyer_id'))->pluck('name', 'id');

        return view('seller.customers', ['customers' => $stats, 'names' => $names]);
    }
}
