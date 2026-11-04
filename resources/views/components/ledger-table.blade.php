@props(['entries', 'showCreator' => false])
<div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
            <tr>
                <th class="px-4 py-3">{{ __('Date') }}</th>
                <th class="px-4 py-3">{{ __('Type') }}</th>
                <th class="px-4 py-3">{{ __('Details') }}</th>
                <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            @forelse ($entries as $entry)
                <tr>
                    <td class="px-4 py-2 whitespace-nowrap text-stone-600">{{ $entry->created_at->format('d M Y') }}</td>
                    <td class="px-4 py-2">{{ $entry->type->label() }}</td>
                    <td class="px-4 py-2">{{ $entry->note }} @if ($showCreator && $entry->creator) <span class="text-xs text-stone-500">· {{ $entry->creator->name }}</span> @endif</td>
                    <td @class(['px-4 py-2 text-right', 'text-emerald-700' => $entry->amount > 0, 'text-stone-700' => $entry->amount <= 0])>{{ $entry->amount > 0 ? '+' : '' }}<x-money :amount="$entry->amount" /></td>
                    <td class="px-4 py-2 text-right font-medium"><x-money :amount="$entry->balance_after" /></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">{{ __('No entries yet. Sales are credited when an order is delivered.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
