@props(['title' => null, 'minimal' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ $siteName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-semibold">{{ $siteName }}</a>
            @unless ($minimal)
            <nav class="flex items-center gap-4 text-sm">
                @auth
                    @if (auth()->user()->role === \App\Enums\Role::Buyer)
                        <a href="{{ route('account.profile') }}" class="hover:underline">{{ auth()->user()->name }}</a>
                    @else
                        <a href="{{ route(auth()->user()->role->homeRoute()) }}" class="hover:underline">{{ __('My dashboard') }}</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="hover:underline">{{ __('Log out') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="hover:underline">{{ __('Log in') }}</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-stone-900 px-3 py-1.5 text-white hover:bg-stone-700">{{ __('Sign up') }}</a>
                @endauth
            </nav>
            @endunless
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
        <x-flash />
        {{ $slot }}
    </main>
</body>
</html>
