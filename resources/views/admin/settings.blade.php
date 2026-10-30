@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Settings')" area="admin">
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <section class="space-y-4 rounded-lg border border-stone-200 bg-white p-6">
            <h2 class="font-semibold">{{ __('General') }}</h2>
            <x-form.input name="site_name" :label="__('Site name')" :value="$settings['site_name']" required />
            <x-form.input name="support_email" type="email" :label="__('Support email')" :value="$settings['support_email']" :hint="__('Shown to buyers and sellers as the contact address.')" />
            <x-form.input name="order_notification_email" type="email" :label="__('Order notification email')" :value="$settings['order_notification_email']" :hint="__('Receives new seller and new order alerts.')" />
        </section>

        <section class="space-y-4 rounded-lg border border-stone-200 bg-white p-6">
            <h2 class="font-semibold">{{ __('Money') }}</h2>
            <x-form.input name="default_commission_percent" :label="__('Default commission (%)')" :value="Money::toRupees($settings['default_commission_bps'])" inputmode="decimal" required :hint="__('Can be overridden per seller.')" />
            <x-form.input name="flat_shipping_rupees" :label="__('Flat shipping per seller (₹)')" :value="Money::toRupees($settings['flat_shipping_paise'])" inputmode="decimal" required />
            <x-form.input name="free_shipping_threshold_rupees" :label="__('Free shipping above (₹, per seller)')" :value="Money::toRupees($settings['free_shipping_threshold_paise'])" inputmode="decimal" required />
            <x-form.checkbox name="cod_enabled" :label="__('Allow Cash on Delivery')" :checked="$settings['cod_enabled']" />
        </section>

        <section class="space-y-4 rounded-lg border border-stone-200 bg-white p-6">
            <h2 class="font-semibold">{{ __('Approvals') }}</h2>
            <x-form.checkbox name="auto_approve_sellers" :label="__('Auto-approve new sellers')" :checked="$settings['auto_approve_sellers']" :hint="__('When off, each new store waits for your review.')" />
            <x-form.checkbox name="auto_approve_products" :label="__('Auto-approve products')" :checked="$settings['auto_approve_products']" :hint="__('When off, new and edited products wait for your review.')" />
        </section>

        <x-form.button>{{ __('Save settings') }}</x-form.button>
    </form>
</x-layouts.panel>
