@php
    $titles = [
        'buyer' => __('Log in'),
        'seller' => __('Seller log in'),
        'admin' => __('Admin log in'),
    ];
@endphp
<x-layouts.store :title="$titles[$role->value]" :minimal="$role !== \App\Enums\Role::Buyer">
    <x-auth-card :title="$titles[$role->value]">
        <form method="POST" action="{{ route($role->loginRoute()) }}" class="space-y-4">
            @csrf
            <x-form.input name="email" type="email" :label="__('Email')" required autofocus autocomplete="username" />
            <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="current-password" />
            <x-form.checkbox name="remember" :label="__('Keep me logged in')" />
            <div class="flex items-center justify-between">
                <a href="{{ route('password.request') }}" class="text-sm text-stone-600 hover:underline">{{ __('Forgot your password?') }}</a>
                <x-form.button>{{ __('Log in') }}</x-form.button>
            </div>
        </form>

        @if ($role === \App\Enums\Role::Buyer)
            <p class="mt-6 text-sm text-stone-600">
                {{ __('New here?') }} <a href="{{ route('register') }}" class="font-medium text-stone-900 hover:underline">{{ __('Create an account') }}</a>
            </p>
        @endif
    </x-auth-card>
</x-layouts.store>
