@props(['title' => null, 'minimal' => false, 'description' => null, 'canonical' => null, 'image' => null, 'noindex' => false, 'ogType' => 'website', 'bleed' => false])
@php
    $metaTitle = $title ? $title.' | '.$siteName : $siteName.' · '.__('Shop independent Indian sellers in one cart');
    $metaDescription = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description ?: __(':site brings independent Indian sellers together: electronics, handloom sarees, home décor, tea and spices. One cart, one checkout, Cash on Delivery available.', ['site' => $siteName])))), 158);
    $metaUrl = $canonical ?: url()->current();
    $isBuyerOrGuest = ! auth()->check() || auth()->user()->role === \App\Enums\Role::Buyer;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($noindex)
        <meta name="robots" content="noindex, follow">
    @endif
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    <meta name="theme-color" content="#0e3b36">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title ?: $siteName }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'hi' ? 'hi_IN' : 'en_IN' }}">
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            ['@type' => 'Organization', '@id' => url('/').'#org', 'name' => $siteName, 'url' => url('/'), 'logo' => url('/favicon.svg')],
            ['@type' => 'WebSite', '@id' => url('/').'#site', 'name' => $siteName, 'url' => url('/'), 'publisher' => ['@id' => url('/').'#org'],
                'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @stack('jsonld')
</head>
<body class="flex min-h-screen flex-col bg-ground font-sans text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:font-semibold">{{ __('Skip to content') }}</a>

    @unless ($minimal)
        <div class="bg-brand-950 text-[13px] text-brand-100">
            <div class="wrap flex flex-wrap items-center justify-between gap-x-6 gap-y-1 py-2">
                <span class="hidden sm:inline">{{ __('Pay once, each seller ships their own parcel · Cash on Delivery available') }}</span>
                <span class="sm:hidden">{{ __('Cash on Delivery available') }}</span>
                <nav class="flex gap-4" aria-label="{{ __('Quick links') }}">
                    <a href="{{ route('seller.register') }}" class="font-semibold text-white hover:underline">{{ __('Sell on :site', ['site' => $siteName]) }}</a>
                    @if ($isBuyerOrGuest)
                        <a href="{{ route('account.orders') }}" class="hidden hover:underline sm:inline">{{ __('Track order') }}</a>
                    @endif
                </nav>
            </div>
        </div>
    @endunless

    <header class="bg-brand-900 text-white">
        <div class="wrap flex flex-wrap items-center gap-x-5 gap-y-3 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-display text-2xl font-extrabold tracking-tight text-white" aria-label="{{ $siteName }} {{ __('home') }}">
                <x-icon name="bag" :size="28" class="text-saffron-500" stroke-width="2.2" />
                {{ $siteName }}
            </a>
            @unless ($minimal)
                <form action="{{ route('search') }}" method="GET" role="search" class="order-last flex h-11 w-full min-w-0 overflow-hidden rounded-lg bg-white md:order-none md:w-auto md:flex-1">
                    <label for="site-search" class="sr-only">{{ __('Search products') }}</label>
                    <input id="site-search" name="q" type="search" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('Search sarees, earbuds, tea, sellers…') }}" autocomplete="off" enterkeyhint="search"
                           class="min-w-0 flex-1 border-0 px-4 text-[15px] text-ink placeholder:text-stone-500 focus:outline-none">
                    <button class="flex w-14 items-center justify-center bg-saffron-500 text-saffron-950 hover:bg-saffron-400" aria-label="{{ __('Search') }}">
                        <x-icon name="search" :size="22" stroke-width="2.4" />
                    </button>
                </form>
                <nav class="ml-auto flex items-center gap-1 text-sm" aria-label="{{ __('Account') }}">
                    @auth
                        @if (auth()->user()->role === \App\Enums\Role::Buyer)
                            <a href="{{ route('account.orders') }}" class="flex min-h-11 flex-col justify-center rounded-md px-2.5 leading-tight hover:bg-brand-800">
                                <span class="text-xs text-brand-100">{{ __('Hello, :name', ['name' => \Illuminate\Support\Str::of(auth()->user()->name)->before(' ')]) }}</span>
                                <span class="font-semibold">{{ __('My orders') }}</span>
                            </a>
                            <a href="{{ route('account.wishlist') }}" class="hidden h-11 w-11 items-center justify-center rounded-md hover:bg-brand-800 sm:flex" aria-label="{{ __('Wishlist') }}"><x-icon name="heart" :size="22" /></a>
                            <a href="{{ route('account.profile') }}" class="hidden h-11 w-11 items-center justify-center rounded-md hover:bg-brand-800 sm:flex" aria-label="{{ __('Account') }}"><x-icon name="user" :size="22" /></a>
                        @else
                            <a href="{{ route(auth()->user()->role->homeRoute()) }}" class="flex min-h-11 items-center rounded-md px-2.5 font-semibold hover:bg-brand-800">{{ __('My dashboard') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="min-h-11 rounded-md px-2.5 text-brand-100 hover:bg-brand-800 hover:text-white">{{ __('Log out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="flex min-h-11 items-center rounded-md px-2.5 leading-tight hover:bg-brand-800" aria-label="{{ __('Log in') }}: {{ __('Account & orders') }}">
                            <x-icon name="user" :size="24" class="sm:hidden" />
                            <span class="hidden flex-col sm:flex">
                                <span class="text-xs text-brand-100">{{ __('Hello, log in') }}</span>
                                <span class="font-semibold">{{ __('Account & orders') }}</span>
                            </span>
                        </a>
                        <a href="{{ route('register') }}" class="hidden min-h-11 items-center rounded-md px-2.5 font-semibold hover:bg-brand-800 lg:flex">{{ __('Sign up') }}</a>
                    @endauth
                    <a href="{{ route('cart') }}" class="flex min-h-11 items-center gap-2 rounded-md px-2.5 font-semibold hover:bg-brand-800">
                        <span class="relative flex">
                            <x-icon name="cart" :size="28" />
                            @if ($cartCount > 0)
                                <span class="absolute -right-2 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-saffron-500 px-1 text-xs font-bold text-saffron-950">{{ $cartCount }}</span>
                            @endif
                        </span>
                        <span><span class="hidden sm:inline">{{ __('Cart') }}</span><span class="sr-only sm:hidden">{{ __('Cart') }}</span><span class="sr-only">, {{ trans_choice(':count item|:count items', $cartCount) }}</span></span>
                    </a>
                </nav>
            @endunless
        </div>
        @if (! $minimal && $navCategories->isNotEmpty())
            <nav class="bg-brand-800" aria-label="{{ __('Categories') }}">
                <div class="wrap flex gap-0.5 overflow-x-auto py-1 [scrollbar-width:none]">
                    <a href="{{ route('search') }}" class="navlink font-semibold">{{ __('All products') }}</a>
                    @foreach ($navCategories as $navCategory)
                        <a href="{{ route('category.show', $navCategory) }}" @class(['navlink', 'bg-brand-700' => request()->is('c/'.$navCategory->slug)])>{{ $navCategory->name }}</a>
                    @endforeach
                    <a href="{{ route('search', ['sort' => 'newest']) }}" class="navlink">{{ __('New arrivals') }}</a>
                </div>
            </nav>
        @endif
    </header>

    <main id="main" class="flex-1">
        @if ($bleed)
            @if (session('status')) <div class="wrap pt-4"><x-flash /></div> @endif
            {{ $slot }}
        @else
            <div class="wrap py-6 sm:py-8">
                <x-flash />
                {{ $slot }}
            </div>
        @endif
    </main>

    <footer class="mt-12 bg-brand-950 text-sm text-brand-100">
        <a href="#main" class="block bg-brand-800 py-3.5 text-center font-semibold text-white hover:bg-brand-700">{{ __('Back to top') }}</a>
        <div class="wrap grid grid-cols-2 gap-8 py-10 md:grid-cols-4">
            <div class="flex flex-col gap-2.5">
                <h2 class="font-semibold text-white">{{ __('Shop') }}</h2>
                @foreach ($navCategories->take(5) as $footerCategory)
                    <a href="{{ route('category.show', $footerCategory) }}" class="hover:text-white hover:underline">{{ $footerCategory->name }}</a>
                @endforeach
            </div>
            <div class="flex flex-col gap-2.5">
                <h2 class="font-semibold text-white">{{ __('Your account') }}</h2>
                <a href="{{ route('account.orders') }}" class="hover:text-white hover:underline">{{ __('Orders & returns') }}</a>
                <a href="{{ route('account.wishlist') }}" class="hover:text-white hover:underline">{{ __('Wishlist') }}</a>
                <a href="{{ route('cart') }}" class="hover:text-white hover:underline">{{ __('Cart') }}</a>
            </div>
            <div class="flex flex-col gap-2.5">
                <h2 class="font-semibold text-white">{{ __('Sell') }}</h2>
                <a href="{{ route('seller.register') }}" class="hover:text-white hover:underline">{{ __('Sell with us') }}</a>
                <a href="{{ route('seller.login') }}" class="hover:text-white hover:underline">{{ __('Seller login') }}</a>
                @if ($hasBlog) <a href="{{ route('blog') }}" class="hover:text-white hover:underline">{{ __('Blog') }}</a> @endif
            </div>
            <div class="flex flex-col gap-2.5">
                <h2 class="font-semibold text-white">{{ __('Help & policies') }}</h2>
                @foreach ($footerPages as $footerPage)
                    <a href="{{ $footerPage->url() }}" class="hover:text-white hover:underline">{{ $footerPage->title }}</a>
                @endforeach
                <a href="{{ route('pages.terms') }}" class="hover:text-white hover:underline">{{ __('Terms') }}</a>
                <a href="{{ route('pages.privacy') }}" class="hover:text-white hover:underline">{{ __('Privacy') }}</a>
                <a href="{{ route('pages.refund') }}" class="hover:text-white hover:underline">{{ __('Refunds & cancellations') }}</a>
            </div>
        </div>
        <div class="border-t border-brand-800">
            <div class="wrap flex flex-wrap items-center justify-between gap-4 py-5 text-[13px] text-brand-100">
                <span>© {{ now()->year }} {{ $siteName }} · {{ __('Secure payments by Razorpay · Cash on Delivery') }}</span>
                <nav class="flex flex-wrap items-center gap-x-4 gap-y-1" aria-label="{{ __('Language and currency') }}">
                    <span class="flex gap-3">
                        @foreach (\App\Support\Locale::LANGUAGES as $code => $name)
                            <a href="{{ route('preferences.language', $code) }}" lang="{{ $code }}" rel="nofollow" @class(['hover:underline', 'font-semibold text-white' => app()->getLocale() === $code])>{{ $name }}</a>
                        @endforeach
                    </span>
                    <span class="flex gap-3">
                        @foreach (['INR', ...array_keys(\App\Support\Locale::rates())] as $code)
                            <a href="{{ route('preferences.currency', $code) }}" rel="nofollow" @class(['hover:underline', 'font-semibold text-white' => \App\Support\Locale::currency() === $code])>{{ $code }}</a>
                        @endforeach
                    </span>
                </nav>
            </div>
        </div>
    </footer>
</body>
</html>
