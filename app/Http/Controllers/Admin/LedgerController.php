<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Store;
use App\Services\Ledger;
use App\Support\Money;
use App\Support\Rules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The worksheet for settling with sellers: payouts are recorded here manually in the MVP.
 */
class LedgerController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->query('store') ? Store::where('slug', $request->query('store'))->first() : null;

        $balances = LedgerEntry::query()
            ->selectRaw('store_id, max(id) as last_id')
            ->groupBy('store_id')
            ->pluck('last_id', 'store_id');

        return view('admin.ledger', [
            'store' => $store,
            'stores' => Store::orderBy('name')->get(),
            'balances' => LedgerEntry::whereIn('id', $balances->values())->pluck('balance_after', 'store_id'),
            'entries' => $store ? $store->ledgerEntries()->with(['subOrder', 'creator'])->orderByDesc('id')->paginate(50)->withQueryString() : null,
        ]);
    }

    public function payout(Request $request, Store $store, Ledger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:payout,adjustment'],
            'amount' => ['required', Rules::RUPEES],
            'direction' => ['required_if:type,adjustment', 'in:credit,debit'],
            'note' => [$request->input('type') === 'adjustment' ? 'required' : 'nullable', 'string', 'max:255'],
        ], ['amount.regex' => __('Enter an amount in rupees, e.g. 1500 or 1500.50.')]);

        $amount = Money::fromRupees($data['amount']);

        if ($amount === 0) {
            return back()->withErrors(['amount' => __('Enter an amount greater than zero.')]);
        }

        if ($data['type'] === 'payout') {
            $ledger->recordPayout($store, $amount, $data['note'] ?? null);
        } else {
            $ledger->adjust($store, $data['direction'] === 'debit' ? -$amount : $amount, $data['note']);
        }

        return redirect()->route('admin.ledger.index', ['store' => $store->slug])->with('status', __('Entry recorded.'));
    }
}
