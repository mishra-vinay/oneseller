<x-layouts.store :bleed="true" :title="__('Your cart')" :noindex="true">
    <div class="wrap pt-6">
        <div class="mb-5 flex flex-wrap items-baseline justify-between gap-2">
            <h1 class="font-display text-2xl font-bold sm:text-3xl">{{ __('Your cart') }}</h1>
            @if ($cart['count'] > 0 && $cart['groups']->count() > 1)
                <p class="text-sm text-muted">{{ __('Your order comes from :count sellers, so it ships as :count separate parcels.', ['count' => $cart['groups']->count()]) }}</p>
            @endif
        </div>

        @error('cart')
            <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
        @enderror

        @if ($cart['count'] === 0)
            <div class="panel flex flex-col items-center gap-3 p-12 text-center">
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-600"><x-icon name="cart" :size="30" /></span>
                <p class="text-lg font-semibold">{{ __('Your cart is empty.') }}</p>
                <a href="{{ route('home') }}" class="btn-primary mt-2">{{ __('Continue shopping') }}</a>
            </div>
        @else
            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div class="flex flex-col gap-4">
                    @foreach ($cart['groups'] as $group)
                        <section class="panel overflow-hidden" aria-label="{{ __('Parcel from :store', ['store' => $group['store']->name]) }}">
                            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-[#f7f8f6] px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <x-store-avatar :store="$group['store']" size="h-9 w-9 text-xs" />
                                    <div class="text-sm leading-tight">
                                        <div class="text-xs text-muted">{{ __('Parcel :n · shipped by', ['n' => $loop->iteration]) }}</div>
                                        <span class="sr-only">{{ __('Sold by') }}</span>
                                        <a href="{{ route('store.show', $group['store']) }}" class="font-bold text-brand-600 hover:underline">{{ $group['store']->name }}</a>
                                        <span class="text-muted">· {{ $group['store']->city }}</span>
                                    </div>
                                </div>
                                <span class="text-[13px] text-muted">{{ __('Shipping') }}: @if ($group['shipping'] === 0) <strong class="text-brand-600">{{ __('Free') }}</strong> @else <x-money :amount="$group['shipping']" /> @endif</span>
                            </header>
                            <ul class="divide-y divide-line">
                                @foreach ($group['items'] as $item)
                                    @php $product = $item->variant->product; @endphp
                                    <li class="flex gap-4 p-4">
                                        <a href="{{ $product->url() }}" class="h-24 w-24 shrink-0 overflow-hidden rounded-lg bg-stone-100" tabindex="-1" aria-hidden="true">
                                            @if ($product->primaryImageUrl()) <img src="{{ $product->primaryImageUrl() }}" alt="" width="96" height="96" loading="lazy" class="h-full w-full object-cover"> @endif
                                        </a>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1">
                                                <a href="{{ $product->url() }}" class="font-semibold hover:text-brand-600">{{ $product->name }}</a>
                                                <x-price :amount="$item->line_total" class="text-lg font-bold" />
                                            </div>
                                            @if ($item->variant->label()) <div class="text-sm text-muted">{{ $item->variant->label() }}</div> @endif
                                            <div class="text-[13px] text-muted"><x-money :amount="$item->variant->price" /> {{ __('each') }}</div>
                                            @if ($item->problem)
                                                <div class="mt-1 text-sm font-semibold text-deal">{{ $item->problem }}</div>
                                            @endif
                                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                                                <form method="POST" action="{{ route('cart.update', $item->id) }}" class="flex items-center gap-2" data-autosubmit>
                                                    @csrf
                                                    @method('PATCH')
                                                    <label for="qty-{{ $item->id }}" class="text-muted">{{ __('Qty') }}</label>
                                                    <input id="qty-{{ $item->id }}" name="quantity" type="number" inputmode="numeric" min="0" max="{{ max(1, $item->variant->stock) }}" value="{{ $item->quantity }}" class="field w-20 text-center">
                                                    <button class="min-h-9 font-semibold text-brand-600 hover:underline">{{ __('Update') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('cart.remove', $item->id) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="min-h-9 font-semibold text-brand-600 hover:underline">{{ __('Remove') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>

                <aside class="flex flex-col gap-4 lg:sticky lg:top-4">
                    <div class="panel p-5">
                        <h2 class="mb-3 font-display text-lg font-bold">{{ __('Order summary') }}</h2>
                        <x-order-totals :items="$cart['items_total']" :shipping="$cart['shipping_total']" :total="$cart['grand_total']" :discount="$cart['discount_total']" :coupon="$cart['coupon']['error'] ? null : $cart['coupon']['code']" />
                        <div class="mt-4 border-t border-line pt-4">
                            @if ($cart['coupon']['code'] && ! $cart['coupon']['error'])
                                <form method="POST" action="{{ route('cart.coupon.remove') }}" class="flex items-center justify-between gap-2 text-sm">
                                    @csrf
                                    @method('DELETE')
                                    <span><span class="font-semibold">{{ $cart['coupon']['code'] }}</span> · {{ $cart['coupon']['summary'] }}</span>
                                    <button class="font-semibold text-brand-600 hover:underline">{{ __('Remove') }}</button>
                                </form>
                            @else
                                @if ($cart['coupon']['error'])
                                    <p class="mb-2 text-sm text-deal">{{ $cart['coupon']['error'] }}</p>
                                @endif
                                <form method="POST" action="{{ route('cart.coupon') }}" class="flex gap-2">
                                    @csrf
                                    <label for="coupon-code" class="sr-only">{{ __('Coupon code') }}</label>
                                    <input id="coupon-code" name="code" value="{{ old('code') }}" placeholder="{{ __('Coupon code') }}" autocomplete="off" class="field uppercase">
                                    <button class="btn-ghost shrink-0 border-brand-600 text-brand-600">{{ __('Apply') }}</button>
                                </form>
                                @error('code') <p class="mt-1 text-sm text-deal">{{ $message }}</p> @enderror
                            @endif
                        </div>
                        @if (\App\Support\Locale::currency() !== 'INR')
                            <p class="mt-3 text-xs text-muted">{{ __('You pay in Indian Rupees (₹). Other currencies are shown for reference only.') }}</p>
                        @endif
                        @if ($cart['has_problems'])
                            <p class="mt-4 text-sm font-semibold text-deal">{{ __('Remove or update the highlighted items to continue.') }}</p>
                        @else
                            <a href="{{ route('checkout.address') }}" class="btn-primary mt-5 w-full text-base">{{ __('Proceed to checkout') }}</a>
                        @endif
                        @guest
                            <p class="mt-3 text-xs text-muted">{{ __('You will be asked to log in or create an account. Your cart stays with you.') }}</p>
                        @endguest
                    </div>
                    <div class="panel flex flex-col gap-3 p-5 text-sm">
                        <div class="flex gap-3"><x-icon name="lock" class="shrink-0 text-brand-600" /> <span>{{ __('Secure online payment by Razorpay: UPI, cards, net banking and wallets') }}</span></div>
                        <div class="flex gap-3"><x-icon name="cash" class="shrink-0 text-brand-600" /> <span>{{ __('Cash on Delivery available') }}</span></div>
                        <div class="flex gap-3"><x-icon name="truck" class="shrink-0 text-brand-600" /> <span>{{ __('Track and return each parcel separately') }}</span></div>
                    </div>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.store>
