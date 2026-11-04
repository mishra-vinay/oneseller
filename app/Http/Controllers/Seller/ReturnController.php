<?php

namespace App\Http\Controllers\Seller;

use App\Enums\ReturnStatus;
use App\Models\ReturnRequest;
use App\Services\Returns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends SellerController
{
    public function __construct(private Returns $returns) {}

    public function index(Request $request): View
    {
        $status = ReturnStatus::tryFrom((string) $request->query('status'));

        return view('seller.returns.index', [
            'returns' => $this->query($request)->with(['items.orderItem', 'subOrder'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(Request $request, string $return): View
    {
        return view('seller.returns.show', ['return' => $this->find($request, $return)->load(['items.orderItem', 'subOrder.order'])]);
    }

    public function approve(Request $request, string $return): RedirectResponse
    {
        $this->returns->approve($this->find($request, $return), $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null);

        return back()->with('status', __('Approved. The buyer has been asked to send the item back.'));
    }

    public function reject(Request $request, string $return): RedirectResponse
    {
        $this->returns->reject($this->find($request, $return), $request->validate(['note' => ['required', 'string', 'max:255']])['note']);

        return back()->with('status', __('Request rejected. The buyer has been told why.'));
    }

    public function receive(Request $request, string $return): RedirectResponse
    {
        $this->returns->markReceived($this->find($request, $return), $request->boolean('restock'));

        return back()->with('status', __('Marked as received.'));
    }

    public function replace(Request $request, string $return): RedirectResponse
    {
        $data = $request->validate([
            'courier' => ['required', 'string', 'max:100'],
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        $this->returns->shipReplacement($this->find($request, $return), $data['courier'], $data['tracking_number']);

        return back()->with('status', __('Replacement shipped. The buyer has the tracking details.'));
    }

    private function query(Request $request)
    {
        return ReturnRequest::whereHas('subOrder', fn ($q) => $q->where('store_id', $this->currentStore($request)->id));
    }

    private function find(Request $request, string $number): ReturnRequest
    {
        return $this->query($request)->where('number', $number)->firstOrFail();
    }
}
