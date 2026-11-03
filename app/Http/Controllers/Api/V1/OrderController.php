<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection($request->user()->orders()->with(['subOrders.store', 'subOrders.items'])->latest()->paginate(15));
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);

        return new OrderResource($order->load(['subOrders.store', 'subOrders.items']));
    }
}
