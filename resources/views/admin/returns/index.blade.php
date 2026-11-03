<x-layouts.panel :title="__('Returns')" area="admin">
    <form method="GET" class="mb-4 text-sm">
        <select name="status" class="rounded-md border border-stone-300 bg-white px-3 py-2" onchange="this.form.submit()" aria-label="{{ __('Status') }}">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\ReturnStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ __($case->label()) }}</option>
            @endforeach
        </select>
    </form>
    <div class="space-y-3">
        @forelse ($returns as $return)
            <a href="{{ route('admin.returns.show', $return) }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-stone-200 bg-white p-4 text-sm hover:border-stone-400">
                <span><span class="font-medium">{{ $return->number }}</span> · {{ __($return->type->label()) }} · {{ $return->subOrder->store->name }} · {{ $return->buyer->name }}</span>
                <span class="flex items-center gap-3"><x-money :amount="$return->refund_amount" /> <x-badge :status="$return->status" /></span>
            </a>
        @empty
            <x-card><p class="text-sm text-stone-500">{{ __('No returns.') }}</p></x-card>
        @endforelse
    </div>
    <div class="mt-4">{{ $returns->links() }}</div>
</x-layouts.panel>
