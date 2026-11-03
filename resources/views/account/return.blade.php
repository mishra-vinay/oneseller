<x-layouts.store :title="__('Return :number', ['number' => $return->number])">
    <x-account-nav />
    <div class="mx-auto max-w-2xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-xl font-semibold">{{ $return->number }}</h1>
            <x-badge :status="$return->status" class="text-sm" />
        </div>
        @if ($return->status === \App\Enums\ReturnStatus::Approved)
            <div class="mb-4 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">{{ __('Approved. Please send the item back to :store, keeping the courier receipt.', ['store' => $return->subOrder->store->name]) }}</div>
        @endif
        <x-card>
            <x-return-summary :return="$return" />
        </x-card>
        <a href="{{ route('order.show', $return->subOrder->order) }}" class="mt-4 inline-block text-sm hover:underline">← {{ __('Back to order :number', ['number' => $return->subOrder->order->number]) }}</a>
    </div>
</x-layouts.store>
