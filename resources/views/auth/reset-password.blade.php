<x-layouts.store :title="__('Reset password')" minimal>
    <x-auth-card :title="__('Choose a new password')">
        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-form.input name="email" type="email" :label="__('Email')" :value="$email" required autocomplete="username" />
            <x-form.input name="password" type="password" :label="__('New password')" required autofocus autocomplete="new-password" />
            <x-form.input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" />
            <x-form.button class="w-full">{{ __('Reset password') }}</x-form.button>
        </form>
    </x-auth-card>
</x-layouts.store>
