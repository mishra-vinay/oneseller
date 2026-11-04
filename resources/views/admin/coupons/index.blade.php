<x-layouts.panel :title="__('Coupons')" area="admin">
    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-sm text-stone-600">{{ __('Discounts come off item prices and are paid by the platform; sellers are credited the full sale.') }}</p>
        <a href="{{ route('admin.coupons.create') }}" class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">{{ __('New coupon') }}</a>
    </div>
    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">{{ __('Code') }}</th>
                    <th class="px-4 py-3">{{ __('Discount') }}</th>
                    <th class="px-4 py-3">{{ __('Applies to') }}</th>
                    <th class="px-4 py-3">{{ __('Valid') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Uses') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Discount given') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($coupons as $coupon)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('admin.coupons.edit', $coupon) }}" class="font-mono font-medium hover:underline">{{ $coupon->code }}</a>
                            @if ($coupon->description) <div class="text-xs text-stone-500">{{ $coupon->description }}</div> @endif</td>
                        <td class="px-4 py-3">{{ $coupon->summary() }} @if ($coupon->min_order_paise) <div class="text-xs text-stone-500">{{ __('min. :amount', ['amount' => \App\Support\Money::format($coupon->min_order_paise)]) }}</div> @endif</td>
                        <td class="px-4 py-3">{{ $coupon->store?->name ?? __('All sellers') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-stone-600">{{ $coupon->starts_at?->format('d M Y') ?? '—' }} → {{ $coupon->ends_at?->format('d M Y') ?? __('no end') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $coupon->uses }}{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : '' }}</td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$coupon->discount_given ?? 0" /></td>
                        <td class="px-4 py-3">
                            @if (! $coupon->is_active) <x-badge status="disabled" />
                            @elseif ($coupon->ends_at?->isPast()) <x-badge status="expired" />
                            @else <x-badge status="active" /> @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-stone-500">{{ __('No coupons yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $coupons->links() }}</div>
</x-layouts.panel>
