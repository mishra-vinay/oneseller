<x-layouts.store :title="__('Forgot password')" minimal>
    <x-auth-card :title="__('Forgot your password?')">
        <p class="mb-4 text-sm text-stone-600">{{ __('Enter your email and we will send you a link to choose a new password.') }}</p>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-form.input name="email" type="email" :label="__('Email')" required autofocus />
            <x-form.button class="w-full">{{ __('Email reset link') }}</x-form.button>
        </form>
    </x-auth-card>
</x-layouts.store>
