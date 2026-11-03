@props(['items', 'shipping', 'total', 'refunded' => 0, 'discount' => 0, 'coupon' => null])
<dl class="space-y-2 text-sm">
    <div class="flex justify-between"><dt>{{ __('Items') }}</dt><dd><x-money :amount="$items" /></dd></div>
    <div class="flex justify-between"><dt>{{ __('Shipping') }}</dt><dd>@if ($shipping === 0) {{ __('Free') }} @else <x-money :amount="$shipping" /> @endif</dd></div>
    @if ($discount > 0)
        <div class="flex justify-between text-emerald-700"><dt>{{ $coupon ? __('Coupon :code', ['code' => $coupon]) : __('Discount') }}</dt><dd>−<x-money :amount="$discount" /></dd></div>
    @endif
    <div class="flex justify-between border-t border-stone-200 pt-2 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd><x-money :amount="$total" /></dd></div>
    @if ($refunded > 0)
        <div class="flex justify-between text-violet-800"><dt>{{ __('Refunded') }}</dt><dd>−<x-money :amount="$refunded" /></dd></div>
    @endif
</dl>
