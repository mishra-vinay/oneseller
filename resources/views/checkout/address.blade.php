<x-layouts.store :title="__('Checkout')" minimal>
    <div class="mx-auto max-w-2xl">
        <x-checkout-steps :current="1" />
        <h1 class="mb-4 text-xl font-semibold">{{ __('Where should we deliver?') }}</h1>
        <form method="POST" action="{{ route('checkout.address.choose') }}" class="space-y-4">
            @csrf
            @php $default = $selected ?? $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id; $chosen = old('address_id', $default ?? 'new'); @endphp
            @foreach ($addresses as $address)
                <label class="flex cursor-pointer gap-3 rounded-lg border border-stone-200 bg-white p-4 has-[:checked]:border-stone-900">
                    <input type="radio" name="address_id" value="{{ $address->id }}" @checked((string) $chosen === (string) $address->id) class="mt-1">
                    <span class="text-sm">
                        <span class="font-medium">{{ $address->name }}</span> · {{ $address->phone }} @if ($address->is_default) <x-badge status="default" /> @endif
                        <span class="block text-stone-600">{{ $address->oneLine() }}</span>
                    </span>
                </label>
            @endforeach
            <div class="rounded-lg border border-stone-200 bg-white p-4 has-[:checked]:border-stone-900">
                <label class="flex cursor-pointer items-center gap-3 text-sm font-medium">
                    <input type="radio" name="address_id" value="new" @checked($chosen === 'new')> {{ __('Deliver to a new address') }}
                </label>
                <div class="mt-4">
                    <x-address-fields :states="$states" :required="$addresses->isEmpty()" />
                    <p class="mt-2 text-xs text-stone-500">{{ __('Only needed if you chose a new address.') }}</p>
                </div>
            </div>
            @error('address_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex items-center justify-between">
                <a href="{{ route('cart') }}" class="text-sm hover:underline">← {{ __('Back to cart') }}</a>
                <x-form.button>{{ __('Continue to review') }}</x-form.button>
            </div>
        </form>
    </div>
</x-layouts.store>
