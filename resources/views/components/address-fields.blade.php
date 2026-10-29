@props(['states', 'address' => null, 'required' => true])
<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="name" :label="__('Full name')" :value="$address?->name ?? auth()->user()->name" :required="$required" autocomplete="name" />
    <x-form.input name="phone" type="tel" :label="__('Mobile number')" :value="$address?->phone ?? auth()->user()->phone" :required="$required" inputmode="numeric" maxlength="10" />
    <div class="sm:col-span-2"><x-form.input name="address_line1" :label="__('House / flat, street')" :value="$address?->address_line1" :required="$required" autocomplete="address-line1" /></div>
    <div class="sm:col-span-2"><x-form.input name="address_line2" :label="__('Area, landmark (optional)')" :value="$address?->address_line2" autocomplete="address-line2" /></div>
    <x-form.input name="city" :label="__('City')" :value="$address?->city" :required="$required" autocomplete="address-level2" />
    <x-form.select name="state" :label="__('State')" :options="array_combine($states, $states)" :value="$address?->state" :placeholder="__('Choose a state')" :required="$required" />
    <x-form.input name="pincode" :label="__('PIN code')" :value="$address?->pincode" :required="$required" inputmode="numeric" maxlength="6" autocomplete="postal-code" />
    <div class="flex items-end pb-2"><x-form.checkbox name="is_default" :label="__('Make this my default address')" :checked="$address?->is_default" /></div>
</div>
