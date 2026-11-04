<x-layouts.panel :title="__('Notifications')" :area="$area">
    <form method="POST" action="{{ route($area.'.notifications.read') }}" class="mb-4">
        @csrf
        <button class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm hover:bg-stone-50">{{ __('Mark all as read') }}</button>
    </form>
    <div class="rounded-lg border border-stone-200 bg-white">
        <ul class="divide-y divide-stone-100 text-sm">
            @forelse ($notifications as $notification)
                <li>
                    <a href="{{ route($area.'.notifications.open', $notification->id) }}" @class(['flex justify-between gap-3 px-4 py-3 hover:bg-stone-50', 'font-medium' => ! $notification->read_at])>
                        <span>@unless ($notification->read_at) <span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-600"></span> @endunless {{ $notification->data['title'] ?? '' }}</span>
                        <span class="shrink-0 text-stone-500">{{ $notification->created_at->diffForHumans() }}</span>
                    </a>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-stone-500">{{ __('No notifications yet.') }}</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.panel>
