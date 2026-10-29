@props(['title', 'area'])
@php
    $nav = $area === 'admin'
        ? [['admin.dashboard', __('Dashboard')], ['admin.settings', __('Settings')]]
        : [['seller.dashboard', __('Dashboard')]];
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
                       @class(['rounded-md px-3 py-2 whitespace-nowrap', 'bg-stone-900 text-white' => request()->routeIs($route), 'hover:bg-stone-100' => ! request()->routeIs($route)])>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex-1">
            <header class="flex items-center justify-between border-b border-stone-200 bg-white px-4 py-3">
                <h1 class="text-lg font-semibold">{{ $title }}</h1>
                <form method="POST" action="{{ route('logout') }}" class="text-sm">
                    @csrf
                    <span class="mr-3 text-stone-500">{{ auth()->user()->name }}</span>
                    <button class="hover:underline">{{ __('Log out') }}</button>
                </form>
            </header>
            <main class="px-4 py-6">
                <x-flash />
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
