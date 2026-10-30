@php use App\Enums\OrderStatus; use App\Enums\SubOrderStatus; @endphp
<x-layouts.store :title="__('Order :number', ['number' => $order->number])">
    @if (session('placed'))
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-5">
            <h1 class="text-xl font-semibold text-emerald-900">{{ __('Thank you! Your order is confirmed.') }}</h1>
            <p class="mt-1 text-sm text-emerald-900">{{ __('Order number :number. We have emailed you the details.', ['number' => $order->number]) }}</p>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-semibold">{{ $order->number }}</h2>
            <p class="text-sm text-stone-500">{{ __('Placed :date', ['date' => ($order->placed_at ?? $order->created_at)->format('d M Y, g:i A')]) }} · {{ $order->payment_method->label() }} · {{ $order->payment_status->label() }}</p>
        </div>
        <x-badge :status="$order->status" class="text-sm" />
    </div>

    @if ($order->status === OrderStatus::PendingPayment)
        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ __('We have not received payment for this order yet.') }}
            <a href="{{ route('checkout.pay', $order) }}" class="font-medium underline">{{ __('Complete payment') }}</a>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-4">
            @foreach ($order->subOrders as $subOrder)
                <section class="rounded-lg border border-stone-200 bg-white">
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-4 py-3 text-sm">
                        <span>{{ __('Parcel :number from', ['number' => $subOrder->number]) }} <span class="font-medium">{{ $subOrder->store->name }}</span></span>
                        <x-badge :status="$subOrder->status" />
                    </header>
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($subOrder->items as $item)
                            <li class="flex justify-between gap-4 px-4 py-3"><span>{{ $item->quantity }} × {{ $item->product_name }}</span><x-money :amount="$item->line_total" /></li>
                        @endforeach
                    </ul>
                    <footer class="space-y-1 border-t border-stone-100 px-4 py-3 text-sm text-stone-600">
                        <div>{{ __('Shipping') }}: @if ($subOrder->shipping === 0) {{ __('Free') }} @else <x-money :amount="$subOrder->shipping" /> @endif</div>
                        @if ($subOrder->tracking_number)
                            <div>{{ __('Courier') }}: <span class="text-stone-900">{{ $subOrder->courier }}</span> · {{ __('Tracking') }}: <span class="font-medium text-stone-900">{{ $subOrder->tracking_number }}</span></div>
                        @endif
                        @if ($subOrder->status === SubOrderStatus::Delivered)
                            <div>{{ __('Delivered :date', ['date' => $subOrder->delivered_at->format('d M Y')]) }}</div>
                        @elseif ($subOrder->status === SubOrderStatus::Cancelled)
                            <div class="text-red-700">{{ __('Cancelled: :reason', ['reason' => $subOrder->cancel_reason]) }} @if ($subOrder->refunded_at) · {{ __('Refunded') }} @endif</div>
                        @endif
                    </footer>
                </section>
            @endforeach
        </div>
        <aside class="space-y-4">
            <x-card :title="__('Summary')">
                <x-order-totals :items="$order->items_total" :shipping="$order->shipping_total" :total="$order->grand_total" :refunded="$order->refunded_total" />
            </x-card>
            <x-card :title="__('Delivery address')">
                @foreach ($order->shippingAddressLines() as $line)
                    <p class="text-sm">{{ $line }}</p>
                @endforeach
            </x-card>
        </aside>
    </div>
</x-layouts.store>
