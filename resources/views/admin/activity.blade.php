<x-layouts.panel :title="__('Activity')" area="admin">
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['' => __('All'), 'seller' => __('Seller registrations'), 'store' => __('Store reviews'), 'product' => __('Products'), 'order' => __('Orders')] as $prefix => $label)
            <a href="{{ route('admin.activity', array_filter(['event' => $prefix])) }}" @class(['rounded-full px-3 py-1', 'bg-stone-900 text-white' => request('event', '') === $prefix, 'border border-stone-300 bg-white' => request('event', '') !== $prefix])>{{ $label }}</a>
        @endforeach
    </div>
    <div class="rounded-lg border border-stone-200 bg-white">
        <ul class="divide-y divide-stone-100 text-sm">
            @forelse ($activities as $activity)
                <li class="flex flex-wrap justify-between gap-2 px-4 py-3">
                    <span>{{ $activity->description }} @if ($activity->actor) <span class="text-stone-500">· {{ $activity->actor->name }}</span> @endif</span>
                    <span class="text-stone-500" title="{{ $activity->created_at }}">{{ $activity->created_at->format('d M Y, g:i A') }}</span>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-stone-500">{{ __('No activity yet.') }}</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $activities->links() }}</div>
</x-layouts.panel>
