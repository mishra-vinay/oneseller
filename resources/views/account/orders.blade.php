<x-layouts.store :title="__('My orders')">
    <x-account-nav />
    @forelse ($orders as $order)
        <a href="{{ route('order.show', $order) }}" class="mb-3 block rounded-lg border border-stone-200 bg-white p-4 hover:border-stone-400">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <span class="font-medium">{{ $order->number }}</span>
                    <span class="ml-2 text-sm text-stone-500">{{ $order->created_at->format('d M Y') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <x-badge :status="$order->status" />
                    <x-money :amount="$order->grand_total" class="font-medium" />
                </div>
            </div>
            <div class="mt-2 flex flex-wrap gap-2 text-xs text-stone-600">
                @foreach ($order->subOrders as $subOrder)
                    <span class="rounded bg-stone-100 px-2 py-1">{{ $subOrder->store->name }}: {{ $subOrder->status->label() }}</span>
                @endforeach
            </div>
        </a>
    @empty
        <div class="rounded-lg border border-stone-200 bg-white p-8 text-center text-sm text-stone-600">
            {{ __('You have not placed any orders yet.') }} <a href="{{ route('home') }}" class="font-medium hover:underline">{{ __('Start shopping') }}</a>
        </div>
    @endforelse
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.store>
