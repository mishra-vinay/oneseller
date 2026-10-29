<x-layouts.panel :title="__('Buyers')" area="admin">
    <form method="GET" class="mb-4 flex gap-2 text-sm">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('Name, email or phone') }}" class="rounded-md border border-stone-300 px-3 py-2">
        <button class="rounded-md border border-stone-300 bg-white px-3 py-2">{{ __('Search') }}</button>
    </form>
    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Email') }}</th>
                    <th class="px-4 py-3">{{ __('Phone') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Orders') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total spent') }}</th>
                    <th class="px-4 py-3">{{ __('Registered') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($buyers as $buyer)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('admin.buyers.show', $buyer) }}" class="font-medium hover:underline">{{ $buyer->name }}</a></td>
                        <td class="px-4 py-3">{{ $buyer->email }}</td>
                        <td class="px-4 py-3">{{ $buyer->phone }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $buyer->orders_count }}</td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$buyer->total_spent ?? 0" /></td>
                        <td class="px-4 py-3">{{ $buyer->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3"><x-badge :status="$buyer->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-stone-500">{{ __('No buyers found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $buyers->links() }}</div>
</x-layouts.panel>
