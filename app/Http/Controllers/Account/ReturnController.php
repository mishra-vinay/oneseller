<?php

namespace App\Http\Controllers\Account;

use App\Enums\ReturnType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Services\Returns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(private Returns $returns) {}

    public function index(Request $request): View
    {
        return view('account.returns', [
            'returns' => ReturnRequest::where('buyer_id', $request->user()->id)->with('subOrder.store')->latest()->paginate(20),
        ]);
    }

    public function show(Request $request, ReturnRequest $return): View
    {
        abort_unless($return->buyer_id === $request->user()->id, 404);

        return view('account.return', ['return' => $return->load(['items.orderItem', 'subOrder.store', 'subOrder.order'])]);
    }

    public function create(Request $request, Order $order, int $subOrder): View|RedirectResponse
    {
        $parcel = $this->parcel($request, $order, $subOrder);

        if (! $this->returns->windowOpen($parcel)) {
            return redirect()->route('order.show', $order)->withErrors(['items' => __('Returns for this parcel are closed.')]);
        }

        return view('account.return-create', [
            'order' => $order,
            'subOrder' => $parcel,
            'items' => $this->returns->returnableItems($parcel->load('items')),
        ]);
    }

    public function store(Request $request, Order $order, int $subOrder): RedirectResponse
    {
        $parcel = $this->parcel($request, $order, $subOrder);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
            'type' => ['required', Rule::enum(ReturnType::class)],
            'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = $this->returns->request($request->user(), $parcel, $data['items'], ReturnType::from($data['type']), $data['reason'], $data['details'] ?? null);

        return redirect()->route('account.returns.show', $return)->with('status', __('Request sent to :store. We will email you when they respond.', ['store' => $parcel->store->name]));
    }

    private function parcel(Request $request, Order $order, int $subOrder): SubOrder
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);

        return $order->subOrders()->with('store')->findOrFail($subOrder);
    }
}
