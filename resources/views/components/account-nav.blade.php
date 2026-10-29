<nav class="mb-6 flex gap-2 overflow-x-auto text-sm">
    @foreach ([['account.orders', __('Orders')], ['account.addresses', __('Addresses')], ['account.profile', __('Profile')]] as [$route, $label])
        <a href="{{ route($route) }}" @class(['whitespace-nowrap rounded-full px-4 py-1.5', 'bg-stone-900 text-white' => request()->routeIs($route.'*'), 'bg-white border border-stone-200 hover:border-stone-400' => ! request()->routeIs($route.'*')])>{{ $label }}</a>
    @endforeach
</nav>
