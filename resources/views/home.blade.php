<x-layouts.store :bleed="true" :canonical="route('home')"
    :description="__(':site brings independent Indian sellers together: electronics, handloom sarees, home décor, tea and spices. One cart, one checkout, Cash on Delivery available.', ['site' => $siteName])">

    {{-- Hero --}}
    <section class="wrap grid gap-4 pt-5 lg:grid-cols-3">
        <div class="relative flex min-h-[320px] flex-col justify-center gap-4 overflow-hidden rounded-2xl bg-maroon p-7 text-white sm:p-12 lg:col-span-2">
            <div aria-hidden="true" class="absolute -right-16 -top-16 h-[420px] w-[420px] rounded-full border-[36px] border-[#a8323f] opacity-60"></div>
            <div aria-hidden="true" class="absolute -bottom-32 right-20 h-[300px] w-[300px] rounded-full border-[24px] border-[#e0a53a] opacity-50"></div>
            <span class="relative self-start rounded-full bg-saffron-500 px-3 py-1 text-[13px] font-bold text-saffron-950">{{ __('Approved independent sellers') }}</span>
            <h1 class="relative max-w-xl font-display text-3xl font-extrabold leading-[1.08] sm:text-5xl">{{ __('Many sellers. One cart. One checkout.') }}</h1>
            <p class="relative max-w-lg text-base text-[#f6dcdf] sm:text-lg">{{ __('Handloom from Kanchipuram, gadgets from Bengaluru, tea from the Nilgiris. Pay once, and each seller ships their own parcel.') }}</p>
            <div class="relative flex flex-wrap gap-3">
                <a href="{{ route('search') }}" class="btn-primary">{{ __('Shop all products') }}</a>
                <a href="{{ route('seller.register') }}" class="inline-flex min-h-11 items-center rounded-lg border-[1.5px] border-[#f6dcdf] px-5 font-semibold text-white hover:bg-white/10">{{ __('Sell on :site', ['site' => $siteName]) }}</a>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            @foreach ($categories->take(2) as $promo)
                <a href="{{ route('category.show', $promo) }}" @class([
                    'flex flex-col justify-center gap-1.5 rounded-2xl border p-6 transition hover:-translate-y-0.5',
                    'border-brand-900 bg-brand-900 text-white' => $loop->first,
                    'border-saffron-200 bg-saffron-50 text-ink' => ! $loop->first,
                ])>
                    <span @class(['text-[13px] font-semibold uppercase tracking-wide', 'text-brand-100' => $loop->first, 'text-[#8a5a00]' => ! $loop->first])>{{ $promo->name }}</span>
                    <span class="font-display text-2xl font-bold leading-tight">{{ $promo->children->pluck('name')->take(3)->implode(', ') ?: $promo->name }}</span>
                    <span @class(['font-semibold', 'text-saffron-500' => $loop->first, 'text-brand-600' => ! $loop->first])>{{ __('Shop now') }} →</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Trust strip --}}
    <section class="wrap pt-4" aria-label="{{ __('Why shop here') }}">
        <ul class="panel grid sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['bag', __('One cart, many sellers'), __('Pay once for every store')],
                ['cash', __('Cash on Delivery'), __('Or pay online with Razorpay')],
                ['truck', __('Track each parcel'), __('Every seller ships separately')],
                ['shield', __('Approved sellers only'), __('Every store is reviewed')],
            ] as [$icon, $heading, $sub])
                <li class="flex items-center gap-3 px-5 py-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon :name="$icon" /></span>
                    <div><div class="text-sm font-semibold">{{ $heading }}</div><div class="text-[13px] text-muted">{{ $sub }}</div></div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="wrap pt-10">
            <div class="mb-4 flex items-baseline justify-between gap-3">
                <h2 class="section-title">{{ __('Shop by category') }}</h2>
                <a href="{{ route('search') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('All products') }}</a>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
                @foreach ($categories as $category)
                    <a href="{{ route('category.show', $category) }}" class="group panel flex flex-col gap-2.5 p-3 transition hover:shadow-[0_6px_20px_rgba(16,32,31,.10)]">
                        <div class="aspect-[4/3] overflow-hidden rounded-lg bg-stone-200">
                            @if ($category->imageUrl())
                                <img src="{{ $category->imageUrl() }}" alt="" width="400" height="300" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                            @endif
                        </div>
                        <div class="px-1">
                            <div class="font-display font-bold group-hover:text-brand-600">{{ $category->name }}</div>
                            @if ($category->children->isNotEmpty())
                                <div class="line-clamp-1 text-[13px] text-muted">{{ $category->children->pluck('name')->implode(' · ') }}</div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Deals --}}
    @if ($deals->isNotEmpty())
        <section class="wrap pt-10">
            <div class="panel p-4 sm:p-5">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="section-title">{{ __("Today's deals") }} <span class="font-sans text-sm font-semibold text-muted">· {{ __('biggest savings on MRP') }}</span></h2>
                    <a href="{{ route('search', ['sort' => 'price_asc']) }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('See all') }}</a>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
                    @foreach ($deals as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- New arrivals --}}
    <section class="wrap pt-10">
        <div class="mb-4 flex items-baseline justify-between gap-3">
            <h2 class="section-title">{{ __('New arrivals') }}</h2>
            <a href="{{ route('search') }}" class="text-sm font-semibold text-brand-600 hover:underline">{{ __('See all') }}</a>
        </div>
        @if ($newArrivals->isEmpty())
            <p class="panel p-6 text-sm text-muted">{{ __('The catalogue opens soon.') }}</p>
        @else
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
                @foreach ($newArrivals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Sellers --}}
    @if ($featuredStores->isNotEmpty())
        <section class="wrap pt-10">
            <div class="mb-4 flex items-baseline justify-between gap-3">
                <h2 class="section-title">{{ __('Shop from independent sellers') }}</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($featuredStores as $store)
                    <a href="{{ route('store.show', $store) }}" class="group panel flex flex-col overflow-hidden transition hover:shadow-[0_6px_20px_rgba(16,32,31,.10)]">
                        <div aria-hidden="true" class="h-20 bg-gradient-to-r from-brand-900 to-brand-700"></div>
                        <div class="flex flex-col gap-1.5 px-5 pb-5">
                            <x-store-avatar :store="$store" size="h-14 w-14 text-lg" class="-mt-7 border-4 border-white" />
                            <div class="flex items-center gap-1.5">
                                <span class="font-display text-lg font-bold group-hover:text-brand-600">{{ $store->name }}</span>
                                <x-icon name="badge" :size="18" class="text-brand-600" />
                                <span class="sr-only">{{ __('Approved seller') }}</span>
                            </div>
                            <div class="text-[13px] text-muted">{{ $store->city }}{{ $store->state ? ', '.$store->state : '' }} · {{ trans_choice(':count product|:count products', $store->visible_products_count) }}</div>
                            @if ($store->description)
                                <p class="line-clamp-2 text-sm leading-relaxed">{{ $store->description }}</p>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Sell banner --}}
    <section class="wrap pt-10">
        <div class="flex flex-wrap items-center justify-between gap-5 rounded-2xl border border-saffron-200 bg-saffron-50 px-6 py-8 sm:px-10">
            <div class="max-w-2xl">
                <h2 class="font-display text-2xl font-bold sm:text-3xl">{{ __('Run a shop? Sell on :site.', ['site' => $siteName]) }}</h2>
                <p class="mt-2 text-[#4a3a1a] sm:text-lg">{{ __('Open your store, list products and ship your own orders. We run the storefront, checkout and payments, and settle your share after delivery.') }}</p>
            </div>
            <a href="{{ route('seller.register') }}" class="btn-dark">{{ __('Start selling') }}</a>
        </div>
    </section>
</x-layouts.store>
