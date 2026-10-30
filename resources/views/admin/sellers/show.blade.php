@php use App\Enums\StoreStatus; use App\Support\Money; @endphp
<x-layouts.panel :title="$store->name" area="admin">
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <x-badge :status="$store->status" />
        <a href="{{ route('admin.sellers.index') }}" class="text-stone-600 hover:underline">← {{ __('All sellers') }}</a>
        @if ($store->isApproved())
            <a href="{{ route('store.show', $store) }}" class="text-stone-600 hover:underline" target="_blank">{{ __('View store page') }} ↗</a>
        @endif
        <a href="{{ route('admin.products.index', ['status' => 'all', 'store' => $store->slug]) }}" class="text-stone-600 hover:underline">{{ __('Products (:count)', ['count' => $store->products_count]) }}</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Store details')" class="lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-stone-500">{{ __('Seller') }}</dt><dd>{{ $store->user->name }}</dd></div>
                <div><dt class="text-stone-500">{{ __('Email') }}</dt><dd>{{ $store->user->email }}</dd></div>
                <div><dt class="text-stone-500">{{ __('Seller phone') }}</dt><dd>{{ $store->user->phone }}</dd></div>
                <div><dt class="text-stone-500">{{ __('Store phone') }}</dt><dd>{{ $store->phone }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-stone-500">{{ __('Address') }}</dt><dd>{{ collect([$store->address_line1, $store->address_line2, $store->city, $store->state.' '.$store->pincode])->filter()->implode(', ') }}</dd></div>
                <div><dt class="text-stone-500">{{ __('GSTIN') }}</dt><dd>{{ $store->gstin ?: '—' }}</dd></div>
                <div><dt class="text-stone-500">{{ __('Web address') }}</dt><dd>/store/{{ $store->slug }}</dd></div>
                <div><dt class="text-stone-500">{{ __('Registered') }}</dt><dd>{{ $store->created_at->format('d M Y, H:i') }}</dd></div>
                <div><dt class="text-stone-500">{{ __('Orders') }}</dt><dd>{{ $store->sub_orders_count }}</dd></div>
                <div><dt class="text-stone-500">{{ __('Balance payable') }}</dt><dd><x-money :amount="$balance" /> · <a href="{{ route('admin.ledger.index', ['store' => $store->slug]) }}" class="hover:underline">{{ __('ledger') }}</a></dd></div>
                @if ($store->rejection_reason)
                    <div class="sm:col-span-2"><dt class="text-stone-500">{{ __('Reason given') }}</dt><dd>{{ $store->rejection_reason }}</dd></div>
                @endif
            </dl>
        </x-card>

        <div class="space-y-6">
            <x-card :title="__('Actions')">
                <div class="space-y-4">
                    @if (in_array($store->status, [StoreStatus::Pending, StoreStatus::Rejected], true))
                        <form method="POST" action="{{ route('admin.sellers.approve', $store) }}">
                            @csrf
                            <x-form.button class="w-full">{{ __('Approve store') }}</x-form.button>
                        </form>
                    @endif
                    @if ($store->status === StoreStatus::Pending)
                        <form method="POST" action="{{ route('admin.sellers.reject', $store) }}" class="space-y-2">
                            @csrf
                            <x-form.input name="reason" :label="__('Rejection reason')" required />
                            <button class="w-full rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Reject') }}</button>
                        </form>
                    @endif
                    @if ($store->status === StoreStatus::Approved)
                        <form method="POST" action="{{ route('admin.sellers.suspend', $store) }}" class="space-y-2">
                            @csrf
                            <x-form.input name="reason" :label="__('Suspension reason')" required />
                            <button class="w-full rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Suspend store') }}</button>
                            <p class="text-xs text-stone-500">{{ __('Hides all products and logs the seller out.') }}</p>
                        </form>
                    @endif
                    @if ($store->status === StoreStatus::Suspended)
                        <form method="POST" action="{{ route('admin.sellers.reactivate', $store) }}">
                            @csrf
                            <x-form.button class="w-full">{{ __('Reactivate store') }}</x-form.button>
                        </form>
                    @endif
                </div>
            </x-card>

            <x-card :title="__('Commission')">
                <form method="POST" action="{{ route('admin.sellers.commission', $store) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <x-form.input name="commission_percent" :label="__('Commission for this seller (%)')" :value="$store->commission_bps !== null ? Money::toRupees($store->commission_bps) : null"
                        :hint="__('Leave empty to use the platform default (:rate%). Applies to new orders.', ['rate' => Money::toRupees($defaultCommissionBps)])" inputmode="decimal" />
                    <x-form.button>{{ __('Save commission') }}</x-form.button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
