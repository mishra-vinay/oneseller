<x-layouts.store :title="__('Review your order')" minimal>
    <div class="mx-auto max-w-3xl">
        <x-checkout-steps :current="2" />
        <div class="grid gap-6 md:grid-cols-[1fr_18rem]">
            <div class="space-y-4">
                <x-card :title="__('Delivering to')">
                    <p class="text-sm"><span class="font-medium">{{ $address->name }}</span> · {{ $address->phone }}</p>
                    <p class="text-sm text-stone-600">{{ $address->oneLine() }}</p>
                    <a href="{{ route('checkout.address') }}" class="mt-2 inline-block text-sm hover:underline">{{ __('Change') }}</a>
                </x-card>
                @foreach ($cart['groups'] as $group)
                    <x-card>
                        <div class="mb-3 flex justify-between text-sm">
                            <span>{{ __('Parcel :n from', ['n' => $loop->iteration]) }} <span class="font-medium">{{ $group['store']->name }}</span></span>
                            <span class="text-stone-500">{{ __('Shipping') }}: @if ($group['shipping'] === 0) {{ __('Free') }} @else <x-money :amount="$group['shipping']" /> @endif</span>
                        </div>
                        <ul class="space-y-2 text-sm">
                            @foreach ($group['items'] as $item)
                                <li class="flex justify-between gap-4"><span>{{ $item->quantity }} × {{ $item->variant->product->name }}</span><x-money :amount="$item->line_total" /></li>
                            @endforeach
                        </ul>
                    </x-card>
                @endforeach
            </div>

            <form method="POST" action="{{ route('checkout.place') }}" class="h-fit space-y-4 rounded-lg border border-stone-200 bg-white p-5">
                @csrf
                <x-order-totals :items="$cart['items_total']" :shipping="$cart['shipping_total']" :total="$cart['grand_total']" />
                <fieldset class="space-y-2 border-t border-stone-200 pt-4">
                    <legend class="mb-2 text-sm font-medium">{{ __('Payment') }}</legend>
                    @forelse ($methods as $method)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="payment_method" value="{{ $method->value }}" @checked($loop->first) required> {{ $method->label() }}
                        </label>
                    @empty
                        <p class="text-sm text-red-700">{{ __('Payments are not available right now. Please try again later.') }}</p>
                    @endforelse
                    @error('payment_method') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>
                @if ($methods)
                    <x-form.button class="w-full">{{ __('Place order') }}</x-form.button>
                @endif
                <p class="text-xs text-stone-500">{!! __('By placing your order you agree to our <a href=":terms" class="underline">terms</a> and <a href=":refund" class="underline">refund policy</a>.', ['terms' => route('pages.terms'), 'refund' => route('pages.refund')]) !!}</p>
            </form>
        </div>
    </div>
</x-layouts.store>
