@php use App\Enums\ShippingMode; use App\Support\Money; $rupees = fn ($paise) => $paise === null ? null : Money::toRupees($paise); $slabs = $store->shipping_slabs ?? []; @endphp
<x-layouts.panel :title="__('Shipping')" area="seller">
    <form method="POST" action="{{ route('seller.shipping.update') }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')
        <x-card :title="__('How you charge for shipping')">
            <p class="mb-4 text-sm text-stone-600">{{ __('Shipping is charged once per order for your parcel, and you receive it along with the sale.') }}</p>
            <div class="space-y-3">
                @foreach (ShippingMode::cases() as $mode)
                    <label class="flex items-start gap-3 text-sm">
                        <input type="radio" name="shipping_mode" value="{{ $mode->value }}" @checked(old('shipping_mode', $store->shipping_mode->value) === $mode->value) class="mt-1">
                        <span>
                            <span class="font-medium">{{ __($mode->label()) }}</span>
                            @if ($mode === ShippingMode::Platform)
                                <span class="block text-stone-500">{{ __(':flat per order, free above :free.', ['flat' => Money::format($platformFlat), 'free' => Money::format($platformFreeAbove)]) }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('Flat rate')">
            <x-form.input name="flat_rupees" :label="__('Shipping per order (₹)')" :value="$rupees($store->shipping_flat_paise)" inputmode="decimal" :hint="__('Used when you choose your own flat rate. 0 means free shipping.')" />
        </x-card>

        <x-card :title="__('Rate card by weight')">
            <p class="mb-3 text-sm text-stone-600">{{ __('The parcel weight is the total of your products\' weights. The first slab that fits is charged.') }}</p>
            <div class="space-y-2">
                @foreach (range(0, \App\Http\Controllers\Seller\ShippingController::SLAB_ROWS - 1) as $i)
                    <div class="grid grid-cols-2 gap-3">
                        <x-form.input :name="'slabs['.$i.'][up_to_grams]'" type="number" min="1" :label="__('Up to (grams)')" :value="old('slabs.'.$i.'.up_to_grams', $slabs[$i]['up_to_grams'] ?? '')" />
                        <x-form.input :name="'slabs['.$i.'][rupees]'" :label="__('Charge (₹)')" :value="old('slabs.'.$i.'.rupees', isset($slabs[$i]) ? $rupees($slabs[$i]['paise']) : '')" inputmode="decimal" />
                    </div>
                @endforeach
            </div>
            @error('slabs') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="mt-4">
                <x-form.input name="extra_kg_rupees" :label="__('Each extra kg above the heaviest slab (₹)')" :value="$rupees($store->shipping_extra_kg_paise)" inputmode="decimal" />
            </div>
        </x-card>

        <x-card :title="__('Free shipping')">
            <x-form.input name="free_above_rupees" :label="__('Free shipping when your parcel totals at least (₹)')" :value="$rupees($store->shipping_free_above_paise)" inputmode="decimal"
                :hint="__('Applies to your own flat rate or rate card. Leave empty to always charge shipping.')" />
        </x-card>

        <x-form.button>{{ __('Save shipping rules') }}</x-form.button>
    </form>
</x-layouts.panel>
