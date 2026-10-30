@php use App\Enums\SubOrderStatus; $order = $subOrder->order; @endphp
<x-layouts.panel :title="__('Order :number', ['number' => $subOrder->number])" area="seller">
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <x-badge :status="$subOrder->status" />
        <span class="text-stone-600">{{ $subOrder->created_at->format('d M Y, g:i A') }} · {{ $order->payment_method->label() }}</span>
        <a href="{{ route('seller.orders.index') }}" class="text-stone-600 hover:underline">← {{ __('All orders') }}</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Items')">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($subOrder->items as $item)
                            <tr>
                                <td class="py-2">{{ $item->product_name }} <span class="text-xs text-stone-500">{{ $item->sku }}</span></td>
                                <td class="py-2 text-right">{{ $item->quantity }} × <x-money :amount="$item->unit_price" /></td>
                                <td class="py-2 text-right"><x-money :amount="$item->line_total" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <dl class="mt-4 space-y-1 border-t border-stone-200 pt-3 text-sm">
                    <div class="flex justify-between"><dt>{{ __('Items subtotal') }}</dt><dd><x-money :amount="$subOrder->items_subtotal" /></dd></div>
                    <div class="flex justify-between"><dt>{{ __('Shipping charged to buyer') }}</dt><dd><x-money :amount="$subOrder->shipping" /></dd></div>
                    <div class="flex justify-between text-stone-600"><dt>{{ __('Commission (:rate%)', ['rate' => rtrim(rtrim(number_format($subOrder->commission_bps / 100, 2), '0'), '.')]) }}</dt><dd>−<x-money :amount="$subOrder->commission_amount" /></dd></div>
                    <div class="flex justify-between font-semibold"><dt>{{ __('Net payout') }}</dt><dd><x-money :amount="$subOrder->net_payout" /></dd></div>
                </dl>
            </x-card>
            <x-card :title="__('Deliver to')">
                @foreach ($order->shippingAddressLines() as $line)
                    <p class="text-sm">{{ $line }}</p>
                @endforeach
            </x-card>
        </div>

        <x-card :title="__('Next step')">
            @switch($subOrder->status)
                @case(SubOrderStatus::Placed)
                    <form method="POST" action="{{ route('seller.orders.accept', $subOrder->id) }}">
                        @csrf
                        <x-form.button class="w-full">{{ __('Accept order') }}</x-form.button>
                    </form>
                    @break
                @case(SubOrderStatus::Accepted)
                    <form method="POST" action="{{ route('seller.orders.ship', $subOrder->id) }}" class="space-y-3">
                        @csrf
                        <x-form.input name="courier" :label="__('Courier name')" required />
                        <x-form.input name="tracking_number" :label="__('Tracking number')" required />
                        <x-form.button class="w-full">{{ __('Mark shipped') }}</x-form.button>
                        <p class="text-xs text-stone-500">{{ __('The buyer is emailed the tracking details.') }}</p>
                    </form>
                    @break
                @case(SubOrderStatus::Shipped)
                    <p class="mb-3 text-sm">{{ $subOrder->courier }} · {{ $subOrder->tracking_number }}</p>
                    <form method="POST" action="{{ route('seller.orders.deliver', $subOrder->id) }}">
                        @csrf
                        <x-form.button class="w-full">{{ __('Mark delivered') }}</x-form.button>
                    </form>
                    @break
                @case(SubOrderStatus::Delivered)
                    <p class="text-sm">{{ __('Delivered :date. The sale is credited to your earnings.', ['date' => $subOrder->delivered_at->format('d M Y')]) }}</p>
                    @break
                @case(SubOrderStatus::Cancelled)
                    <p class="text-sm">{{ __('Cancelled by :who: :reason', ['who' => $subOrder->cancelled_by, 'reason' => $subOrder->cancel_reason]) }}</p>
                    @break
            @endswitch

            @if ($subOrder->status->canBeCancelled())
                <form method="POST" action="{{ route('seller.orders.cancel', $subOrder->id) }}" class="mt-6 space-y-2 border-t border-stone-200 pt-4" onsubmit="return confirm('{{ __('Cancel this order? The buyer will be notified.') }}')">
                    @csrf
                    <x-form.input name="reason" :label="__('Cancellation reason')" required />
                    <button class="w-full rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Cancel order') }}</button>
                </form>
            @endif
        </x-card>
    </div>
</x-layouts.panel>
