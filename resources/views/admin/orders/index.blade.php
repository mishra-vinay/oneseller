<x-layouts.panel :title="__('Orders')" area="admin">
    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Order number or buyer email') }}" class="rounded-md border border-stone-300 px-3 py-2">
        <select name="status" class="rounded-md border border-stone-300 bg-white px-3 py-2" onchange="this.form.submit()">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\OrderStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-stone-300 bg-white px-3 py-2">{{ __('Filter') }}</button>
    </form>
    <div class="space-y-3">
        @forelse ($orders as $order)
            <a href="{{ route('admin.orders.show', $order) }}" class="block rounded-lg border border-stone-200 bg-white p-4 hover:border-stone-400">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span><span class="font-medium">{{ $order->number }}</span> · {{ $order->buyer->name }} · {{ $order->created_at->format('d M Y, g:i A') }}</span>
                    <span class="flex items-center gap-2"><x-badge :status="$order->status" /> <x-badge :status="$order->payment_status" /> <x-money :amount="$order->grand_total" class="font-medium" /></span>
                </div>
                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                    @foreach ($order->subOrders as $subOrder)
                        <span class="rounded bg-stone-100 px-2 py-1">{{ $subOrder->number }} · {{ $subOrder->store->name }} · {{ $subOrder->status->label() }}</span>
                    @endforeach
                </div>
            </a>
        @empty
            <x-card><p class="text-sm text-stone-500">{{ __('No orders found.') }}</p></x-card>
        @endforelse
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.panel>
