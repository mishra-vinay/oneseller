<x-layouts.store :title="__('Payment')" minimal>
    <div class="mx-auto max-w-md">
        <x-checkout-steps :current="3" />
        <x-card>
            <h1 class="text-lg font-semibold">{{ __('Pay for order :number', ['number' => $order->number]) }}</h1>
            <p class="mt-1 text-sm text-stone-600">{{ __('Amount') }}: <x-money :amount="$order->grand_total" class="font-semibold" /></p>
            @error('payment') <p class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800">{{ $message }}</p> @enderror

            @if ($payment)
                <button id="pay-button" type="button" class="mt-5 w-full rounded-md bg-stone-900 px-4 py-2.5 font-medium text-white hover:bg-stone-700">{{ __('Pay now') }}</button>
                <p class="mt-3 text-xs text-stone-500">{{ __('Your items are reserved for :min minutes while you pay.', ['min' => \App\Services\OrderPayments::PAYMENT_WINDOW_MINUTES]) }}</p>
                <form id="pay-callback" method="POST" action="{{ route('checkout.pay.callback', $order) }}" class="hidden">
                    @csrf
                    <input type="hidden" name="razorpay_order_id">
                    <input type="hidden" name="razorpay_payment_id">
                    <input type="hidden" name="razorpay_signature">
                </form>
                <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                <script>
                    (function () {
                        const form = document.getElementById('pay-callback');
                        const rzp = new Razorpay({
                            key: @json($keyId),
                            amount: @json($order->grand_total),
                            currency: 'INR',
                            name: @json($siteName),
                            description: @json('Order '.$order->number),
                            order_id: @json($payment->gateway_order_id),
                            prefill: { name: @json(auth()->user()->name), email: @json(auth()->user()->email), contact: @json(auth()->user()->phone) },
                            handler: function (response) {
                                form.razorpay_order_id.value = response.razorpay_order_id;
                                form.razorpay_payment_id.value = response.razorpay_payment_id;
                                form.razorpay_signature.value = response.razorpay_signature;
                                form.submit();
                            },
                        });
                        document.getElementById('pay-button').addEventListener('click', function () { rzp.open(); });
                        rzp.open();
                    })();
                </script>
            @else
                <p class="mt-4 text-sm text-red-700">{{ __('We could not start the payment. Please try again in a moment.') }}</p>
                <a href="{{ route('checkout.pay', $order) }}" class="mt-3 inline-block text-sm font-medium hover:underline">{{ __('Try again') }}</a>
            @endif
        </x-card>
    </div>
</x-layouts.store>
