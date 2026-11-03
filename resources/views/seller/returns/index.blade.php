<x-layouts.panel :title="__('Returns')" area="seller">
    <form method="GET" class="mb-4 text-sm">
        <select name="status" class="rounded-md border border-stone-300 bg-white px-3 py-2" onchange="this.form.submit()" aria-label="{{ __('Status') }}">
            <option value="">{{ __('All statuses') }}</option>
            @foreach (\App\Enums\ReturnStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected($status === $case)>{{ __($case->label()) }}</option>
            @endforeach
        </select>
    </form>
    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr><th class="px-4 py-3">{{ __('Return') }}</th><th class="px-4 py-3">{{ __('Items') }}</th><th class="px-4 py-3">{{ __('Type') }}</th><th class="px-4 py-3">{{ __('Reason') }}</th><th class="px-4 py-3">{{ __('Status') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($returns as $return)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('seller.returns.show', $return) }}" class="font-medium hover:underline">{{ $return->number }}</a><div class="text-xs text-stone-500">{{ $return->created_at->format('d M Y') }}</div></td>
                        <td class="px-4 py-3">@foreach ($return->items as $line)<div>{{ $line->quantity }} × {{ $line->orderItem->displayName() }}</div>@endforeach</td>
                        <td class="px-4 py-3">{{ __($return->type->label()) }}</td>
                        <td class="px-4 py-3">{{ __($return->reasonLabel()) }}</td>
                        <td class="px-4 py-3"><x-badge :status="$return->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">{{ __('No return requests.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $returns->links() }}</div>
</x-layouts.panel>
