<x-layouts.panel :title="__('Seller earnings')" area="admin">
    <div class="grid gap-6 lg:grid-cols-[18rem_1fr]">
        <x-card :title="__('Balances')" class="h-fit">
            <ul class="divide-y divide-stone-100 text-sm">
                @forelse ($stores as $s)
                    <li>
                        <a href="{{ route('admin.ledger.index', ['store' => $s->slug]) }}" @class(['flex justify-between gap-2 py-2 hover:underline', 'font-semibold' => $store?->is($s)])>
                            <span class="truncate">{{ $s->name }}</span>
                            <x-money :amount="$balances[$s->id] ?? 0" />
                        </a>
                    </li>
                @empty
                    <li class="py-2 text-stone-500">{{ __('No sellers yet.') }}</li>
                @endforelse
            </ul>
            <p class="mt-3 text-xs text-stone-500">{{ __('Positive: the platform owes the seller. Negative: the seller owes commission on COD orders.') }}</p>
        </x-card>

        <div>
            @if ($store)
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ $store->name }}</h2>
                    <span>{{ __('Balance') }}: <x-money :amount="$balances[$store->id] ?? 0" class="font-semibold" /></span>
                </div>
                <x-card :title="__('Record a payout or adjustment')" class="mb-4">
                    <form method="POST" action="{{ route('admin.ledger.payout', $store) }}" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                        @csrf
                        <x-form.select name="type" :label="__('Type')" :options="['payout' => __('Payout to seller'), 'adjustment' => __('Adjustment')]" />
                        <x-form.select name="direction" :label="__('Adjustment direction')" :options="['credit' => __('Credit seller (+)'), 'debit' => __('Debit seller (−)')]" />
                        <x-form.input name="amount" :label="__('Amount (₹)')" inputmode="decimal" required />
                        <x-form.input name="note" :label="__('Note / bank reference')" />
                        <div class="sm:col-span-4"><x-form.button>{{ __('Record entry') }}</x-form.button></div>
                    </form>
                </x-card>
                <x-ledger-table :entries="$entries" show-creator />
                <div class="mt-4">{{ $entries->links() }}</div>
            @else
                <x-card><p class="text-sm text-stone-500">{{ __('Choose a seller to see their statement and record payouts.') }}</p></x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
