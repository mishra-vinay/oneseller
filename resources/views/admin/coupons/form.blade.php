@php use App\Enums\CouponType; use App\Support\Money; $r = fn ($p) => $p === null ? null : Money::toRupees($p); @endphp
<x-layouts.panel :title="$coupon->exists ? __('Edit :code', ['code' => $coupon->code]) : __('New coupon')" area="admin">
    <form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="max-w-2xl space-y-6">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input name="code" :label="__('Code')" :value="$coupon->code" required maxlength="40" class="uppercase" :hint="__('Letters, numbers, dashes. Buyers type this at checkout.')" />
                <x-form.input name="description" :label="__('Internal note')" :value="$coupon->description" />
                <x-form.select name="type" :label="__('Type')" :options="['percent' => __('Percentage off'), 'fixed' => __('Fixed amount off')]" :value="$coupon->type?->value" />
                <x-form.input name="value" :label="__('Value (% or ₹)')" :value="$r($coupon->value)" required inputmode="decimal" />
                <x-form.input name="min_order_rupees" :label="__('Minimum order (₹, optional)')" :value="$r($coupon->min_order_paise)" inputmode="decimal" />
                <x-form.input name="max_discount_rupees" :label="__('Maximum discount (₹, optional)')" :value="$r($coupon->max_discount_paise)" inputmode="decimal" :hint="__('Caps percentage coupons.')" />
                <x-form.select name="store_id" :label="__('Only for one seller (optional)')" :options="$stores->all()" :value="$coupon->store_id" :placeholder="__('All sellers')" />
                <div></div>
                <x-form.input name="usage_limit" type="number" min="1" :label="__('Total uses (optional)')" :value="$coupon->usage_limit" />
                <x-form.input name="per_user_limit" type="number" min="1" :label="__('Uses per buyer (optional)')" :value="$coupon->per_user_limit" />
                <x-form.input name="starts_at" type="date" :label="__('Starts')" :value="$coupon->starts_at?->toDateString()" />
                <x-form.input name="ends_at" type="date" :label="__('Ends')" :value="$coupon->ends_at?->toDateString()" />
            </div>
            <div class="mt-4"><x-form.checkbox name="is_active" :label="__('Active')" :checked="$coupon->is_active" /></div>
        </x-card>
        <div class="flex items-center gap-4">
            <x-form.button>{{ __('Save coupon') }}</x-form.button>
            <a href="{{ route('admin.coupons.index') }}" class="text-sm text-stone-600 hover:underline">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.panel>
