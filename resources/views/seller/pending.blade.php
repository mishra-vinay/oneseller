<x-layouts.store :title="__('Store under review')" minimal>
    <x-auth-card :title="$store->name">
        @switch($store->status)
            @case(\App\Enums\StoreStatus::Rejected)
                <x-badge :status="$store->status" />
                <p class="mt-4 text-sm">{{ __('Your store registration was not approved.') }}</p>
                <p class="mt-2 rounded-md bg-stone-100 px-3 py-2 text-sm"><span class="font-medium">{{ __('Reason:') }}</span> {{ $store->rejection_reason }}</p>
                <p class="mt-4 text-sm text-stone-600">{{ __('Please contact support if you would like us to look again.') }}</p>
                @break
            @default
                <x-badge :status="$store->status" />
                <p class="mt-4 text-sm">{{ __('Thanks for registering. Our team is reviewing your store.') }}</p>
                <p class="mt-2 text-sm text-stone-600">{{ __('We will email you at :email as soon as it is approved. You can then add products from your dashboard.', ['email' => auth()->user()->email]) }}</p>
        @endswitch
        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button class="text-sm text-stone-600 hover:underline">{{ __('Log out') }}</button>
        </form>
    </x-auth-card>
</x-layouts.store>
