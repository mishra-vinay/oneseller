<x-layouts.store :title="__('Unsubscribed')" minimal>
    <x-auth-card :title="__('You are unsubscribed')">
        <p class="text-sm text-stone-600">{{ __('We will not email you about items left in your cart. You can turn reminders back on in your profile.') }}</p>
        <a href="{{ route('home') }}" class="mt-4 inline-block text-sm font-medium hover:underline">{{ __('Back to the shop') }}</a>
    </x-auth-card>
</x-layouts.store>
