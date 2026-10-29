<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.orders', [
            'orders' => $request->user()->orders()->with('subOrders.store')->latest()->paginate(15),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);

        return view('account.order', ['order' => $order->load(['subOrders.store', 'subOrders.items'])]);
    }
}
