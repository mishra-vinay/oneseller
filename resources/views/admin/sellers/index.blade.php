<x-layouts.panel :title="__('Sellers')" area="admin">
    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Store name or city') }}" class="rounded-md border border-stone-300 px-3 py-2">
        <select name="status" class="rounded-md border border-stone-300 bg-white px-3 py-2" onchange="this.form.submit()">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\StoreStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ $case->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-md border border-stone-300 bg-white px-3 py-2">{{ __('Filter') }}</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Store') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Products') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Orders') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Lifetime sales') }}</th>
                    <th class="px-4 py-3">{{ __('Registered') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($stores as $store)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.sellers.show', $store) }}" class="font-medium hover:underline">{{ $store->name }}</a>
                            <div class="text-xs text-stone-500">{{ $store->user->name }} · {{ $store->city }}</div>
                        </td>
                        <td class="px-4 py-3"><x-badge :status="$store->status" /></td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $store->products_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $store->sub_orders_count }}</td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$store->lifetime_sales ?? 0" /></td>
                        <td class="px-4 py-3 text-stone-600">{{ $store->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">{{ __('No sellers found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $stores->links() }}</div>
</x-layouts.panel>
