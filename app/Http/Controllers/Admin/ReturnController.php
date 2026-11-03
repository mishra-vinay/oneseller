<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Returns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Oversight of every return; admins settle disputes and record refunds.
 */
class ReturnController extends Controller
{
    public function __construct(private Returns $returns) {}

    public function index(Request $request): View
    {
        $status = ReturnStatus::tryFrom((string) $request->query('status'));

        return view('admin.returns.index', [
            'returns' => ReturnRequest::with(['subOrder.store', 'buyer'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()->paginate(25)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(ReturnRequest $return): View
    {
        return view('admin.returns.show', ['return' => $return->load(['items.orderItem', 'subOrder.store', 'subOrder.order', 'buyer'])]);
    }

    public function approve(Request $request, ReturnRequest $return): RedirectResponse
    {
        $this->returns->approve($return, $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? __('Approved by the marketplace team.'));

        return back()->with('status', __('Return approved.'));
    }

    public function reject(Request $request, ReturnRequest $return): RedirectResponse
    {
        $this->returns->reject($return, $request->validate(['note' => ['required', 'string', 'max:255']])['note']);

        return back()->with('status', __('Return rejected.'));
    }

    public function refund(ReturnRequest $return): RedirectResponse
    {
        $this->returns->markRefunded($return);

        return back()->with('status', __('Refund recorded and the seller\'s ledger adjusted.'));
    }
}
