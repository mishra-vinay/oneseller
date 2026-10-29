<x-layouts.panel :title="$buyer->name" area="admin">
    <div class="mb-6 flex flex-wrap items-center gap-4 text-sm">
        <x-badge :status="$buyer->status" />
        <span>{{ $buyer->email }} @unless ($buyer->hasVerifiedEmail()) <span class="text-amber-700">({{ __('unverified') }})</span> @endunless</span>
        <span>{{ $buyer->phone }}</span>
        <span class="text-stone-500">{{ __('Registered :date', ['date' => $buyer->created_at->format('d M Y')]) }}</span>
        <form method="POST" action="{{ $buyer->isActive() ? route('admin.buyers.deactivate', $buyer) : route('admin.buyers.reactivate', $buyer) }}" class="ml-auto">
            @csrf
            <button @class(['rounded-md border px-3 py-1.5 font-medium', 'border-red-300 text-red-700 hover:bg-red-50' => $buyer->isActive(), 'border-stone-300 hover:bg-stone-50' => ! $buyer->isActive()])>
                {{ $buyer->isActive() ? __('Deactivate account') : __('Reactivate account') }}
            </button>
        </form>
    </div>
    <x-card :title="__('Order history (read-only)')">
        @forelse ($orders as $order)
            <a href="{{ route('admin.orders.show', $order) }}" class="flex items-center justify-between border-b border-stone-100 py-2 text-sm last:border-0 hover:underline">
                <span>{{ $order->number }} · {{ $order->created_at->format('d M Y') }}</span>
                <span class="flex items-center gap-3"><x-badge :status="$order->status" /> <x-money :amount="$order->grand_total" /></span>
            </a>
        @empty
            <p class="text-sm text-stone-500">{{ __('No orders.') }}</p>
        @endforelse
        <div class="mt-3">{{ $orders->links() }}</div>
    </x-card>
</x-layouts.panel>
