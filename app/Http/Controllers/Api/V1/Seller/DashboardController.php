<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\SubOrderStatus;
use App\Http\Resources\StoreResource;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends SellerApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $live = fn () => $store->subOrders()->whereNotIn('status', [SubOrderStatus::PendingPayment, SubOrderStatus::Cancelled]);

        return response()->json([
            'store' => new StoreResource($store),
            'orders_today' => $live()->where('created_at', '>=', today())->count(),
            'revenue_this_month' => (int) $live()->where('created_at', '>=', now()->startOfMonth())->sum('items_subtotal'),
            'awaiting_action' => $store->subOrders()->whereIn('status', SubOrderStatus::awaitingSeller())->count(),
            'low_stock_variants' => ProductVariant::whereHas('product', fn ($q) => $q->where('store_id', $store->id))
                ->where('stock', '<=', ProductVariant::LOW_STOCK_THRESHOLD)->count(),
            'balance' => $store->balance(),
            'unread_notifications' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
