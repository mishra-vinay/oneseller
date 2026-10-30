@php use App\Enums\PaymentMethod; use App\Enums\PaymentStatus; use App\Enums\SubOrderStatus; @endphp
<x-layouts.panel :title="__('Order :number', ['number' => $order->number])" area="admin">
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <x-badge :status="$order->status" /> <x-badge :status="$order->payment_status" />
        <span class="text-stone-600">{{ $order->payment_method->label() }} · {{ $order->created_at->format('d M Y, g:i A') }}</span>
        <a href="{{ route('admin.buyers.show', $order->buyer) }}" class="hover:underline">{{ $order->buyer->name }} ({{ $order->buyer->email }})</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-4">
            @foreach ($order->subOrders as $subOrder)
                <x-card>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm"><span class="font-semibold">{{ $subOrder->number }}</span> · <a href="{{ route('admin.sellers.show', $subOrder->store) }}" class="hover:underline">{{ $subOrder->store->name }}</a></div>
                        <div class="flex gap-2"><x-badge :status="$subOrder->status" /> @if ($subOrder->refunded_at) <x-badge status="refunded" /> @endif</div>
                    </div>
                    <ul class="space-y-1 text-sm">
                        @foreach ($subOrder->items as $item)
                            <li class="flex justify-between gap-4"><span>{{ $item->quantity }} × {{ $item->product_name }} <span class="text-xs text-stone-500">{{ $item->sku }}</span></span><x-money :amount="$item->line_total" /></li>
                        @endforeach
                    </ul>
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 border-t border-stone-100 pt-3 text-sm sm:grid-cols-4">
                        <div><dt class="text-xs text-stone-500">{{ __('Shipping') }}</dt><dd><x-money :amount="$subOrder->shipping" /></dd></div>
                        <div><dt class="text-xs text-stone-500">{{ __('Commission (:rate%)', ['rate' => $subOrder->commission_bps / 100]) }}</dt><dd><x-money :amount="$subOrder->commission_amount" /></dd></div>
                        <div><dt class="text-xs text-stone-500">{{ __('Net payout') }}</dt><dd><x-money :amount="$subOrder->net_payout" /></dd></div>
                        <div><dt class="text-xs text-stone-500">{{ __('Tracking') }}</dt><dd>{{ $subOrder->tracking_number ? $subOrder->courier.' · '.$subOrder->tracking_number : '—' }}</dd></div>
                    </dl>
                    @if ($subOrder->cancel_reason)
                        <p class="mt-2 text-sm text-red-700">{{ __('Cancelled by :who: :reason', ['who' => $subOrder->cancelled_by, 'reason' => $subOrder->cancel_reason]) }}</p>
                    @endif

                    @if ($subOrder->status->canBeCancelled())
                        <form method="POST" action="{{ route('admin.orders.cancel-portion', [$order, $subOrder->id]) }}" class="mt-3 flex flex-wrap items-end gap-2" onsubmit="return confirm('{{ __('Cancel this seller\'s portion?') }}')">
                            @csrf
                            <div class="flex-1"><x-form.input name="reason" :label="__('Cancel this portion: reason')" required /></div>
                            <button class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Cancel portion') }}</button>
                        </form>
                    @endif
                    @if ($subOrder->status === SubOrderStatus::Cancelled && ! $subOrder->refunded_at && in_array($order->payment_status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded], true))
                        <form method="POST" action="{{ route('admin.orders.refund-portion', [$order, $subOrder->id]) }}" class="mt-3">
                            @csrf
                            <button class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium hover:bg-stone-50">{{ __('Mark :amount refunded', ['amount' => \App\Support\Money::format($subOrder->total())]) }}</button>
                            <span class="ml-2 text-xs text-stone-500">{{ __('Issue the refund in the Razorpay dashboard first.') }}</span>
                        </form>
                    @endif
                </x-card>
            @endforeach
        </div>
        <aside class="space-y-4">
            <x-card :title="__('Totals')">
                <x-order-totals :items="$order->items_total" :shipping="$order->shipping_total" :total="$order->grand_total" :refunded="$order->refunded_total" />
            </x-card>
            <x-card :title="__('Ship to')">
                @foreach ($order->shippingAddressLines() as $line) <p class="text-sm">{{ $line }}</p> @endforeach
            </x-card>
            @if ($order->payment_method === PaymentMethod::Online)
                <x-card :title="__('Payments')">
                    @forelse ($order->payments as $payment)
                        <p class="text-sm">{{ $payment->gateway }} · {{ $payment->status }} · <x-money :amount="$payment->amount" /></p>
                        <p class="break-all text-xs text-stone-500">{{ $payment->gateway_order_id }} {{ $payment->gateway_payment_id }}</p>
                    @empty
                        <p class="text-sm text-stone-500">{{ __('No payment attempts.') }}</p>
                    @endforelse
                </x-card>
            @endif
        </aside>
    </div>
</x-layouts.panel>
