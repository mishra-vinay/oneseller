<x-layouts.panel :title="__('Store profile')" area="seller">
    <form method="POST" action="{{ route('seller.store.update') }}" enctype="multipart/form-data" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="name" :label="__('Store name')" :value="$store->name" required />
                <div>
                    <span class="mb-1 block text-sm font-medium">{{ __('Store web address') }}</span>
                    <a href="{{ route('store.show', $store) }}" class="block truncate rounded-md bg-stone-100 px-3 py-2 text-sm text-stone-700 hover:underline">{{ route('store.show', $store) }}</a>
                    <p class="mt-1 text-xs text-stone-500">{{ __('Fixed, because it appears in live product links.') }}</p>
                </div>
                <div class="sm:col-span-2"><x-form.textarea name="description" :label="__('About your store')" :value="$store->description" /></div>
                <x-form.input name="phone" type="tel" :label="__('Contact phone')" :value="$store->phone" required maxlength="10" />
                <x-form.input name="gstin" :label="__('GSTIN (optional)')" :value="$store->gstin" maxlength="15" class="uppercase" />
                <div class="sm:col-span-2"><x-form.input name="address_line1" :label="__('Business address')" :value="$store->address_line1" required /></div>
                <div class="sm:col-span-2"><x-form.input name="address_line2" :label="__('Address line 2')" :value="$store->address_line2" /></div>
                <x-form.input name="city" :label="__('City')" :value="$store->city" required />
                <x-form.select name="state" :label="__('State')" :options="array_combine($states, $states)" :value="$store->state" required />
                <x-form.input name="pincode" :label="__('PIN code')" :value="$store->pincode" required maxlength="6" />
            </div>
        </x-card>
        <x-card :title="__('Logo')">
            <div class="flex items-center gap-4">
                @if ($store->logoUrl())
                    <img src="{{ $store->logoUrl() }}" alt="" class="h-16 w-16 rounded-md border border-stone-200 object-cover">
                @endif
                <div>
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    @error('logo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-card>
        <x-form.button>{{ __('Save store profile') }}</x-form.button>
    </form>
</x-layouts.panel>
