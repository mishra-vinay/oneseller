@props(['title', 'area'])
@php
    $nav = $area === 'admin'
        ? [
            ['admin.dashboard', __('Dashboard')], ['admin.sellers.index', __('Sellers')], ['admin.buyers.index', __('Buyers')],
            ['admin.products.index', __('Products')], ['admin.categories.index', __('Categories')], ['admin.orders.index', __('Orders')],
            ['admin.ledger.index', __('Seller earnings')], ['admin.activity', __('Activity')], ['admin.settings', __('Settings')],
        ]
        : [
            ['seller.dashboard', __('Dashboard')], ['seller.products.index', __('Products')], ['seller.inventory', __('Inventory')],
            ['seller.orders.index', __('Orders')], ['seller.customers', __('Customers')], ['seller.earnings', __('Earnings')],
            ['seller.store', __('Store profile')],
        ];
    $nav = array_filter($nav, fn ($item) => \Illuminate\Support\Facades\Route::has($item[0]));
    // "admin.sellers.index" stays highlighted on every "admin.sellers.*" page.
    $isActive = fn ($route) => request()->routeIs(str_ends_with($route, '.index') ? substr($route, 0, -6).'.*' : $route);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $siteName }} {{ $area === 'admin' ? __('Admin') : __('Seller') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    <div class="md:flex">
        <aside class="border-b border-stone-200 bg-white md:min-h-screen md:w-56 md:border-r md:border-b-0">
            <div class="px-4 py-4 font-semibold">
                {{ $siteName }}
                <span class="ml-1 rounded bg-stone-200 px-1.5 py-0.5 text-xs font-medium uppercase">{{ $area }}</span>
            </div>
            <nav class="flex gap-1 overflow-x-auto px-2 pb-3 text-sm md:flex-col">
                @foreach ($nav as [$route, $label])
                    <a href="{{ route($route) }}"
                       @class(['rounded-md px-3 py-2 whitespace-nowrap', 'bg-stone-900 text-white' => $isActive($route), 'hover:bg-stone-100' => ! $isActive($route)])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex-1">
            <header class="flex items-center justify-between border-b border-stone-200 bg-white px-4 py-3">
                <h1 class="text-lg font-semibold">{{ $title }}</h1>
                <div class="flex items-center gap-4 text-sm">
                    @if (\Illuminate\Support\Facades\Route::has($area.'.notifications'))
                        <a href="{{ route($area.'.notifications') }}" class="hover:underline">
                            {{ __('Notifications') }}@if ($unreadCount > 0) <span class="ml-1 rounded-full bg-red-600 px-1.5 text-xs font-semibold text-white">{{ $unreadCount }}</span>@endif
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <span class="mr-3 hidden text-stone-500 sm:inline">{{ auth()->user()->name }}</span>
                        <button class="hover:underline">{{ __('Log out') }}</button>
                    </form>
                </div>
            </header>
            <main class="px-4 py-6">
                <x-flash />
                <x-errors />
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
