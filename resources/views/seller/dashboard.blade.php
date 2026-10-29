<x-layouts.panel :title="__('Dashboard')" area="seller">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <x-stat :label="__('Orders today')">{{ $ordersToday }}</x-stat>
        <x-stat :label="__('Revenue this month')"><x-money :amount="$revenueThisMonth" /></x-stat>
        <x-stat :label="__('Waiting for you')" :href="route('seller.orders.index', ['status' => 'placed'])">{{ $awaitingAction }}</x-stat>
        <x-stat :label="__('Low-stock products')" :href="route('seller.inventory', ['low' => 1])">{{ $lowStock->count() }}</x-stat>
        <x-stat :label="__('Earnings balance')" :href="route('seller.earnings')"><x-money :amount="$balance" /></x-stat>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Orders waiting for action')">
            @forelse ($recentOrders as $subOrder)
                <a href="{{ route('seller.orders.show', $subOrder->id) }}" class="flex items-center justify-between border-b border-stone-100 py-2 text-sm last:border-0 hover:underline">
                    <span>{{ $subOrder->number }} · {{ trans_choice(':count item|:count items', $subOrder->items->sum('quantity')) }}</span>
                    <x-badge :status="$subOrder->status" />
                </a>
            @empty
                <p class="text-sm text-stone-500">{{ __('Nothing waiting. Nice work.') }}</p>
            @endforelse
        </x-card>
        <x-card :title="__('Low stock')">
            @forelse ($lowStock as $variant)
                <div class="flex items-center justify-between border-b border-stone-100 py-2 text-sm last:border-0">
                    <a href="{{ route('seller.products.edit', $variant->product_id) }}" class="hover:underline">{{ $variant->product->name }}</a>
                    <span @class(['font-medium tabular-nums', 'text-red-700' => $variant->stock === 0])>{{ $variant->stock }} {{ __('left') }}</span>
                </div>
            @empty
                <p class="text-sm text-stone-500">{{ __('All products are well stocked.') }}</p>
            @endforelse
        </x-card>
    </div>
</x-layouts.panel>
