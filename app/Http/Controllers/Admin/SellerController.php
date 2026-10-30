<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreModeration;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerController extends Controller
{
    public function __construct(private StoreModeration $moderation) {}

    public function index(Request $request): View
    {
        $status = StoreStatus::tryFrom((string) $request->query('status'));

        $stores = Store::query()
            ->with('user')
            ->withCount(['products', 'subOrders'])
            ->withSum(['subOrders as lifetime_sales' => fn ($q) => $q->where('status', 'delivered')], 'items_subtotal')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%")))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.sellers.index', ['stores' => $stores, 'status' => $status]);
    }

    public function show(Store $store, Settings $settings): View
    {
        $store->load('user')->loadCount(['products', 'subOrders']);

        return view('admin.sellers.show', [
            'store' => $store,
            'defaultCommissionBps' => $settings->get('default_commission_bps'),
            'balance' => $store->balance(),
        ]);
    }

    public function approve(Store $store): RedirectResponse
    {
        $this->moderation->approve($store);

        return back()->with('status', __(':store approved.', ['store' => $store->name]));
    }

    public function reject(Request $request, Store $store): RedirectResponse
    {
        $this->moderation->reject($store, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return back()->with('status', __(':store rejected.', ['store' => $store->name]));
    }

    public function suspend(Request $request, Store $store): RedirectResponse
    {
        $this->moderation->suspend($store, $request->validate(['reason' => ['required', 'string', 'max:255']])['reason']);

        return back()->with('status', __(':store suspended. Its products are hidden and the seller is logged out.', ['store' => $store->name]));
    }

    public function reactivate(Store $store): RedirectResponse
    {
        $this->moderation->reactivate($store);

        return back()->with('status', __(':store reactivated.', ['store' => $store->name]));
    }

    public function commission(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate(['commission_percent' => ['nullable', 'regex:/^\d{1,2}(\.\d{1,2})?$/']], [
            'commission_percent.regex' => __('Enter a percentage between 0 and 99.99.'),
        ]);

        $this->moderation->setCommission($store, filled($data['commission_percent'] ?? null) ? Money::fromRupees($data['commission_percent']) : null);

        return back()->with('status', __('Commission updated. It applies to new orders only.'));
    }
}
