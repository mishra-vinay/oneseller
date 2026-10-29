<x-layouts.store :title="__('Create an account')">
    <x-auth-card :title="__('Create an account')">
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <x-form.input name="name" :label="__('Full name')" required autofocus autocomplete="name" />
            <x-form.input name="email" type="email" :label="__('Email')" required autocomplete="email" />
            <x-form.input name="phone" type="tel" :label="__('Mobile number')" required autocomplete="tel-national" inputmode="numeric" maxlength="10" />
            <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" />
            <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
            <x-form.button class="w-full">{{ __('Create account') }}</x-form.button>
        </form>
        <p class="mt-6 text-sm text-stone-600">
            {{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-medium text-stone-900 hover:underline">{{ __('Log in') }}</a>
        </p>
    </x-auth-card>
</x-layouts.store>
