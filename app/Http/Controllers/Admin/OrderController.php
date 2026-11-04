<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Fulfilment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admins can cancel a seller's portion and record refunds, but never edit line items.
 */
class OrderController extends Controller
{
    public function __construct(private Fulfilment $fulfilment) {}

    public function index(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        $orders = Order::query()
            ->with(['buyer', 'subOrders.store'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")->orWhereHas('buyer', fn ($b) => $b->where('email', 'like', "%{$term}%"))))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'status' => $status]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load(['buyer', 'subOrders.store', 'subOrders.items', 'payments'])]);
    }

    public function cancelPortion(Request $request, Order $order, int $subOrder): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        $this->fulfilment->cancel($order->subOrders()->findOrFail($subOrder), $reason, Role::Admin);

        return back()->with('status', __('Portion cancelled. Stock returned and the buyer and seller notified.'));
    }

    public function refundPortion(Order $order, int $subOrder): RedirectResponse
    {
        $this->fulfilment->markRefunded($order->subOrders()->findOrFail($subOrder));

        return back()->with('status', __('Refund recorded.'));
    }
}
