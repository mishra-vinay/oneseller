@props(['return'])
<dl class="grid gap-3 text-sm sm:grid-cols-2">
    <div><dt class="text-stone-500">{{ __('Type') }}</dt><dd>{{ __($return->type->label()) }}</dd></div>
    <div><dt class="text-stone-500">{{ __('Reason') }}</dt><dd>{{ __($return->reasonLabel()) }}</dd></div>
    <div><dt class="text-stone-500">{{ __('Parcel') }}</dt><dd>{{ $return->subOrder->number }} · {{ $return->subOrder->store->name }}</dd></div>
    <div><dt class="text-stone-500">{{ __('Requested') }}</dt><dd>{{ $return->created_at->format('d M Y') }}</dd></div>
    @if ($return->type === \App\Enums\ReturnType::Refund)
        <div><dt class="text-stone-500">{{ __('Refund amount') }}</dt><dd><x-money :amount="$return->refund_amount" class="font-medium" /> <span class="text-xs text-stone-500">{{ __('(shipping is not refunded)') }}</span></dd></div>
    @endif
    @if ($return->details)
        <div class="sm:col-span-2"><dt class="text-stone-500">{{ __('Buyer\'s note') }}</dt><dd class="whitespace-pre-line">{{ $return->details }}</dd></div>
    @endif
    @if ($return->seller_note)
        <div class="sm:col-span-2"><dt class="text-stone-500">{{ __('Seller\'s note') }}</dt><dd>{{ $return->seller_note }}</dd></div>
    @endif
    @if ($return->replacement_tracking)
        <div class="sm:col-span-2"><dt class="text-stone-500">{{ __('Replacement') }}</dt><dd>{{ $return->replacement_courier }} · {{ $return->replacement_tracking }}</dd></div>
    @endif
</dl>
<ul class="mt-4 divide-y divide-stone-100 border-t border-stone-100 text-sm">
    @foreach ($return->items as $line)
        <li class="flex justify-between py-2"><span>{{ $line->quantity }} × {{ $line->orderItem->displayName() }}</span><x-money :amount="$line->orderItem->unit_price * $line->quantity" /></li>
    @endforeach
</ul>
