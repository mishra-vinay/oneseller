<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use App\Services\Fulfilment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A seller only ever sees their own portion of an order: no other seller's items,
 * and the buyer's delivery address and phone but not their email.
 */
class OrderController extends SellerController
{
    public function __construct(private Fulfilment $fulfilment) {}

    public function index(Request $request): View
    {
        $status = SubOrderStatus::tryFrom((string) $request->query('status'));

        $subOrders = $this->currentStore($request)->subOrders()
            ->where('status', '!=', SubOrderStatus::PendingPayment)
            ->with(['items', 'order'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->where('number', 'like', "%{$term}%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('seller.orders.index', ['subOrders' => $subOrders, 'status' => $status]);
    }

    public function show(Request $request, int $subOrder): View
    {
        return view('seller.orders.show', ['subOrder' => $this->find($request, $subOrder)->load(['items', 'order'])]);
    }

    public function accept(Request $request, int $subOrder): RedirectResponse
    {
        $this->fulfilment->accept($this->find($request, $subOrder));

        return back()->with('status', __('Order accepted. Ship it and add the tracking number.'));
    }

    public function ship(Request $request, int $subOrder): RedirectResponse
    {
        $data = $request->validate([
            'courier' => ['required', 'string', 'max:100'],
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        $this->fulfilment->ship($this->find($request, $subOrder), $data['courier'], $data['tracking_number']);

        return back()->with('status', __('Marked as shipped. The buyer has been emailed the tracking details.'));
    }

    public function deliver(Request $request, int $subOrder): RedirectResponse
    {
        $this->fulfilment->deliver($this->find($request, $subOrder));

        return back()->with('status', __('Marked as delivered. The sale is credited to your earnings.'));
    }

    public function cancel(Request $request, int $subOrder): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']])['reason'];

        $this->fulfilment->cancel($this->find($request, $subOrder), $reason, Role::Seller);

        return back()->with('status', __('Order cancelled and stock returned.'));
    }

    private function find(Request $request, int $id): SubOrder
    {
        return $this->currentStore($request)->subOrders()
            ->where('status', '!=', SubOrderStatus::PendingPayment)
            ->findOrFail($id);
    }
}
