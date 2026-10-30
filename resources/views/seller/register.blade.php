<x-layouts.store :title="__('Become a seller')" minimal>
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-semibold">{{ __('Sell on :site', ['site' => $siteName]) }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ __('One form for your login and your store. We review every new store before it goes live.') }}</p>

        <form method="POST" action="{{ route('seller.register') }}" class="mt-6 space-y-6">
            @csrf
            <x-card :title="__('Your login')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="name" :label="__('Your full name')" required autocomplete="name" />
                    <x-form.input name="phone" type="tel" :label="__('Your mobile number')" required inputmode="numeric" maxlength="10" />
                    <div class="sm:col-span-2"><x-form.input name="email" type="email" :label="__('Email')" required autocomplete="email" /></div>
                    <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" />
                    <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
                </div>
            </x-card>

            <x-card :title="__('Your store')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input name="store_name" :label="__('Store name')" required :hint="__('Your store web address is created from this and cannot be changed later.')" />
                    <x-form.input name="store_phone" type="tel" :label="__('Store contact phone')" required inputmode="numeric" maxlength="10" />
                    <div class="sm:col-span-2"><x-form.input name="address_line1" :label="__('Business address')" required /></div>
                    <div class="sm:col-span-2"><x-form.input name="address_line2" :label="__('Address line 2 (optional)')" /></div>
                    <x-form.input name="city" :label="__('City')" required />
                    <x-form.select name="state" :label="__('State')" :options="array_combine($states, $states)" :placeholder="__('Choose a state')" required />
                    <x-form.input name="pincode" :label="__('PIN code')" required inputmode="numeric" maxlength="6" />
                    <x-form.input name="gstin" :label="__('GSTIN (optional)')" maxlength="15" class="uppercase" />
                </div>
            </x-card>

            <div class="flex items-center justify-between">
                <a href="{{ route('seller.login') }}" class="text-sm text-stone-600 hover:underline">{{ __('Already selling? Log in') }}</a>
                <x-form.button>{{ __('Register my store') }}</x-form.button>
            </div>
        </form>
    </div>
</x-layouts.store>
