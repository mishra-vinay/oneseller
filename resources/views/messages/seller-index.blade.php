<x-layouts.panel :title="__('Messages')" area="seller">
    <div class="rounded-lg border border-stone-200 bg-white">
        <ul class="divide-y divide-stone-100 text-sm">
            @forelse ($conversations as $c)
                <li>
                    <a href="{{ route('seller.messages.show', $c->id) }}" class="flex items-center justify-between gap-2 px-4 py-3 hover:bg-stone-50">
                        <span @class(['font-semibold' => $c->unreadFor(\App\Enums\Role::Seller)])>@if ($c->unreadFor(\App\Enums\Role::Seller))<span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-600"></span>@endif {{ $c->buyer->name }}</span>
                        <span class="text-stone-500">{{ $c->last_message_at?->diffForHumans() }}</span>
                    </a>
                </li>
            @empty
                <li class="px-4 py-10 text-center text-stone-500">{{ __('No messages yet.') }}</li>
            @endforelse
        </ul>
    </div>
    <div class="mt-4">{{ $conversations->links() }}</div>
</x-layouts.panel>
