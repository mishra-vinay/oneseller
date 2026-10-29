<x-layouts.panel :title="__('Customers')" area="seller">
    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('City') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Orders') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total spent') }}</th>
                    <th class="px-4 py-3">{{ __('Last order') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-4 py-3">{{ $names[$customer->buyer_id] ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $customer->city }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $customer->order_count }}</td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$customer->total_spent" /></td>
                        <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">{{ __('No customers yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.panel>
