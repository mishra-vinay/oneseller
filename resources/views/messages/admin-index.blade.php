<x-layouts.panel :title="__('Messages')" area="admin">
    <p class="mb-4 text-sm text-stone-600">{{ __('Read-only. Use these to settle disputes between buyers and sellers.') }}</p>
    <div class="rounded-lg border border-stone-200 bg-white">
        <ul class="divide-y divide-stone-100 text-sm">
            @forelse ($conversations as $c)
                <li><a href="{{ route('admin.messages.show', $c) }}" class="flex justify-between gap-2 px-4 py-3 hover:bg-stone-50"><span>{{ $c->buyer->name }} ↔ {{ $c->store->name }}</span><span class="text-stone-500">{{ $c->last_message_at?->diffForHumans() }}</span></a></li>
            @empty
                <li class="px-4 py-10 text-center text-stone-500">{{ __('No conversations.') }}</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $conversations->links() }}</div>
</x-layouts.panel>
