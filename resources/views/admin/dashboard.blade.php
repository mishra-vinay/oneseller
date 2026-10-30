<x-layouts.panel :title="__('Dashboard')" area="admin">
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        <x-stat :label="__('Orders today')" :href="route('admin.orders.index')">{{ $ordersToday }}</x-stat>
        <x-stat :label="__('Sales today')"><x-money :amount="$salesToday" /></x-stat>
        <x-stat :label="__('Products to approve')" :href="route('admin.products.index')">{{ $pendingProducts }}</x-stat>
        <x-stat :label="__('Sellers to approve')" :href="route('admin.sellers.index', ['status' => 'pending'])">{{ $pendingSellers }}</x-stat>
        <x-stat :label="__('Active sellers')" :href="route('admin.sellers.index', ['status' => 'approved'])">{{ $activeSellers }}</x-stat>
        <x-stat :label="__('Buyers')" :href="route('admin.buyers.index')">{{ $buyers }}</x-stat>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <x-card :title="__('Orders, last 7 days')">
            <figure>
                <div class="flex h-48 items-end gap-3" role="img" aria-label="{{ $chart->map(fn ($d) => $d['date'].': '.$d['count'])->implode(', ') }}">
                    @foreach ($chart as $day)
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                            <span class="text-xs font-medium tabular-nums">{{ $day['count'] }}</span>
                            <div class="w-full max-w-12 rounded-t bg-stone-800" style="height: {{ max(2, round($day['count'] / $chartMax * 100)) }}%" title="{{ $day['date'] }}: {{ $day['count'] }}"></div>
                            <span class="text-xs text-stone-500">{{ $day['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </figure>
        </x-card>
        <x-card :title="__('Recent activity')">
            <ul class="space-y-2 text-sm">
                @forelse ($activities as $activity)
                    <li><span class="text-stone-500">{{ $activity->created_at->diffForHumans(short: true) }}</span> · {{ $activity->description }}</li>
                @empty
                    <li class="text-stone-500">{{ __('Nothing yet.') }}</li>
                @endforelse
            </ul>
            <a href="{{ route('admin.activity') }}" class="mt-3 inline-block text-sm hover:underline">{{ __('All activity') }} →</a>
        </x-card>
    </div>
</x-layouts.panel>
