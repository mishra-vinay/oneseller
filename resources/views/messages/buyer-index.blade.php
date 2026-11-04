<x-layouts.store :title="__('Messages')">
    <x-account-nav />
    @forelse ($conversations as $c)
        <a href="{{ route('account.messages.show', $c->id) }}" class="mb-3 flex items-center justify-between gap-2 rounded-lg border border-stone-200 bg-white p-4 text-sm hover:border-stone-400">
            <span @class(['font-semibold' => $c->unreadFor(\App\Enums\Role::Buyer)])>@if ($c->unreadFor(\App\Enums\Role::Buyer))<span class="mr-1 inline-block h-2 w-2 rounded-full bg-red-600"></span>@endif {{ $c->store->name }}</span>
            <span class="text-stone-500">{{ $c->last_message_at?->diffForHumans() }}</span>
        </a>
    @empty
        <p class="rounded-lg border border-stone-200 bg-white p-8 text-center text-sm text-stone-600">{{ __('No messages. You can ask a seller a question from any product page or order.') }}</p>
    @endforelse
    <div class="mt-4">{{ $conversations->links() }}</div>
</x-layouts.store>
