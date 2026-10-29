@props(['title' => null, 'minimal' => false, 'description' => null, 'canonical' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ $siteName }}</title>
    @if ($description)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($description), 160) }}">
    @endif
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-semibold">{{ $siteName }}</a>
            @unless ($minimal)
                <form action="{{ route('search') }}" method="GET" role="search" class="order-last w-full sm:order-none sm:w-auto sm:flex-1">
                    <label for="site-search" class="sr-only">{{ __('Search products') }}</label>
                    <input id="site-search" name="q" type="search" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('Search products') }}"
                           class="w-full rounded-md border border-stone-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-900">
                </form>
                <nav class="ml-auto flex items-center gap-4 text-sm">
                    @auth
                        @if (auth()->user()->role === \App\Enums\Role::Buyer)
                            <a href="{{ route('account.orders') }}" class="hover:underline">{{ __('My orders') }}</a>
                            <a href="{{ route('account.profile') }}" class="hidden hover:underline sm:inline">{{ __('Account') }}</a>
                        @else
                            <a href="{{ route(auth()->user()->role->homeRoute()) }}" class="hover:underline">{{ __('My dashboard') }}</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="hover:underline">{{ __('Log out') }}</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="hover:underline">{{ __('Log in') }}</a>
                        <a href="{{ route('register') }}" class="hidden hover:underline sm:inline">{{ __('Sign up') }}</a>
                    @endauth
                    <a href="{{ route('cart') }}" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">
                        {{ __('Cart') }}@if ($cartCount > 0) <span class="ml-1 rounded-full bg-white px-1.5 text-xs font-semibold text-stone-900">{{ $cartCount }}</span>@endif
                    </a>
                </nav>
            @endunless
        </div>
        @if (! $minimal && $navCategories->isNotEmpty())
            <nav class="border-t border-stone-100" aria-label="{{ __('Categories') }}">
                <div class="mx-auto flex max-w-6xl gap-5 overflow-x-auto px-4 py-2 text-sm text-stone-600">
                    @foreach ($navCategories as $navCategory)
                        <a href="{{ route('category.show', $navCategory) }}" class="whitespace-nowrap hover:text-stone-900">{{ $navCategory->name }}</a>
                    @endforeach
                </div>
            </nav>
        @endif
    </header>

    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <x-flash />
        {{ $slot }}
    </main>

    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-6 text-sm text-stone-600">
            <span>© {{ now()->year }} {{ $siteName }}</span>
            <nav class="flex flex-wrap gap-4">
                <a href="{{ route('seller.register') }}" class="hover:underline">{{ __('Sell with us') }}</a>
                <a href="{{ route('pages.terms') }}" class="hover:underline">{{ __('Terms') }}</a>
                <a href="{{ route('pages.privacy') }}" class="hover:underline">{{ __('Privacy') }}</a>
                <a href="{{ route('pages.refund') }}" class="hover:underline">{{ __('Refunds & cancellations') }}</a>
            </nav>
        </div>
    </footer>
</body>
</html>
