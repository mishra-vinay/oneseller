<x-layouts.store :title="__('Your cart')">
    <h1 class="mb-6 text-2xl font-semibold">{{ __('Your cart') }}</h1>

    @error('cart')
        <div role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
    @enderror

    @if ($cart['count'] === 0)
        <div class="rounded-lg border border-stone-200 bg-white p-8 text-center">
            <p class="text-stone-600">{{ __('Your cart is empty.') }}</p>
            <a href="{{ route('home') }}" class="mt-4 inline-block font-medium hover:underline">{{ __('Continue shopping') }}</a>
        </div>
    @else
        @if ($cart['groups']->count() > 1)
            <p class="mb-4 text-sm text-stone-600">{{ __('Your order comes from :count sellers, so it ships as :count separate parcels.', ['count' => $cart['groups']->count()]) }}</p>
        @endif
        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-4">
                @foreach ($cart['groups'] as $group)
                    <section class="rounded-lg border border-stone-200 bg-white">
                        <header class="flex items-center justify-between border-b border-stone-100 px-4 py-3 text-sm">
                            <span>{{ __('Sold by') }} <a href="{{ route('store.show', $group['store']) }}" class="font-medium hover:underline">{{ $group['store']->name }}</a></span>
                            <span class="text-stone-500">{{ __('Shipping') }}: @if ($group['shipping'] === 0) {{ __('Free') }} @else <x-money :amount="$group['shipping']" /> @endif</span>
                        </header>
                        <ul class="divide-y divide-stone-100">
                            @foreach ($group['items'] as $item)
                                @php $product = $item->variant->product; @endphp
                                <li class="flex gap-4 p-4">
                                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded bg-stone-100">
                                        @if ($product->primaryImageUrl()) <img src="{{ $product->primaryImageUrl() }}" alt="" class="h-full w-full object-cover"> @endif
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ $product->url() }}" class="font-medium hover:underline">{{ $product->name }}</a>
                                        @if ($item->variant->label()) <div class="text-sm text-stone-600">{{ $item->variant->label() }}</div> @endif
                                        <div class="text-sm text-stone-500"><x-money :amount="$item->variant->price" /> {{ __('each') }}</div>
                                        @if ($item->problem)
                                            <div class="mt-1 text-sm font-medium text-red-700">{{ $item->problem }}</div>
                                        @endif
                                        <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                                            <form method="POST" action="{{ route('cart.update', $item->id) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <label for="qty-{{ $item->id }}" class="sr-only">{{ __('Quantity') }}</label>
                                                <input id="qty-{{ $item->id }}" name="quantity" type="number" min="0" max="{{ max(1, $item->variant->stock) }}" value="{{ $item->quantity }}" class="w-16 rounded-md border border-stone-300 px-2 py-1">
                                                <button class="hover:underline">{{ __('Update') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('cart.remove', $item->id) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-stone-600 hover:underline">{{ __('Remove') }}</button>
                                            </form>
                                        </div>
                                    </div>
                                    <x-price :amount="$item->line_total" class="font-medium" />
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            <aside class="h-fit rounded-lg border border-stone-200 bg-white p-5">
                <x-order-totals :items="$cart['items_total']" :shipping="$cart['shipping_total']" :total="$cart['grand_total']" :discount="$cart['discount_total']" :coupon="$cart['coupon']['error'] ? null : $cart['coupon']['code']" />
                <div class="mt-4 border-t border-stone-200 pt-4">
                    @if ($cart['coupon']['code'] && ! $cart['coupon']['error'])
                        <form method="POST" action="{{ route('cart.coupon.remove') }}" class="flex items-center justify-between text-sm">
                            @csrf
                            @method('DELETE')
                            <span><span class="font-medium">{{ $cart['coupon']['code'] }}</span> · {{ $cart['coupon']['summary'] }}</span>
                            <button class="text-stone-600 hover:underline">{{ __('Remove') }}</button>
                        </form>
                    @else
                        @if ($cart['coupon']['error'])
                            <p class="mb-2 text-sm text-red-700">{{ $cart['coupon']['error'] }}</p>
                        @endif
                        <form method="POST" action="{{ route('cart.coupon') }}" class="flex gap-2">
                            @csrf
                            <label for="coupon-code" class="sr-only">{{ __('Coupon code') }}</label>
                            <input id="coupon-code" name="code" value="{{ old('code') }}" placeholder="{{ __('Coupon code') }}" class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm uppercase">
                            <button class="rounded-md border border-stone-300 px-3 py-2 text-sm font-medium hover:bg-stone-50">{{ __('Apply') }}</button>
                        </form>
                        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @endif
                </div>
                @if (\App\Support\Locale::currency() !== 'INR')
                    <p class="mt-3 text-xs text-stone-500">{{ __('You pay in Indian Rupees (₹). Other currencies are shown for reference only.') }}</p>
                @endif
                @if ($cart['has_problems'])
                    <p class="mt-4 text-sm text-red-700">{{ __('Remove or update the highlighted items to continue.') }}</p>
                @else
                    <a href="{{ route('checkout.address') }}" class="mt-5 block rounded-md bg-stone-900 px-4 py-2.5 text-center font-medium text-white hover:bg-stone-700">{{ __('Proceed to checkout') }}</a>
                @endif
                @guest
                    <p class="mt-3 text-xs text-stone-500">{{ __('You will be asked to log in or create an account. Your cart stays with you.') }}</p>
                @endguest
            </aside>
        </div>
    @endif
</x-layouts.store>
