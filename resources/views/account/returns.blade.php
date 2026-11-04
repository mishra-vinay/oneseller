<x-layouts.store :title="__('My returns')">
    <x-account-nav />
    @forelse ($returns as $return)
        <a href="{{ route('account.returns.show', $return) }}" class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-stone-200 bg-white p-4 text-sm hover:border-stone-400">
            <span><span class="font-medium">{{ $return->number }}</span> · {{ __($return->type->label()) }} · {{ $return->subOrder->store->name }}</span>
            <x-badge :status="$return->status" />
        </a>
    @empty
        <p class="rounded-lg border border-stone-200 bg-white p-8 text-center text-sm text-stone-600">{{ __('No returns. You can request one from a delivered order.') }}</p>
    @endforelse
    <div class="mt-4">{{ $returns->links() }}</div>
</x-layouts.store>
