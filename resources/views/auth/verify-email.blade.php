<x-layouts.store :title="__('Verify your email')">
    <x-auth-card :title="__('Verify your email')">
        <p class="mb-4 text-sm text-stone-600">
            {{ __('Before you can check out, please confirm your email address using the link we sent to :email.', ['email' => auth()->user()->email]) }}
        </p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-form.button>{{ __('Resend verification email') }}</x-form.button>
        </form>
    </x-auth-card>
</x-layouts.store>
