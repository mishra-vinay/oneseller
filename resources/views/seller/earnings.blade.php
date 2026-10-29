<x-layouts.panel :title="__('Earnings')" area="seller">
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-stat :label="$balance >= 0 ? __('Balance payable to you') : __('Balance you owe (commission on COD orders)')"><x-money :amount="abs($balance)" /></x-stat>
        <div class="rounded-lg border border-stone-200 bg-white p-4 text-sm text-stone-600">
            {{ __('Sales are credited when an order is delivered, minus commission. For Cash on Delivery your courier collects the money, so those orders show the commission you owe. Payouts are made by bank transfer and recorded here.') }}
        </div>
    </div>
    <x-ledger-table :entries="$entries" />
    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.panel>
