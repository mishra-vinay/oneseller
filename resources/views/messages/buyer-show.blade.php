<x-layouts.store :title="__('Messages with :store', ['store' => $conversation->store->name])">
    <x-account-nav />
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-4 text-xl font-semibold">{{ $conversation->store->name }}</h1>
        @include('messages.thread', [
            'viewerId' => auth()->id(),
            'replyAction' => route('account.messages.reply', $conversation->id),
            'senderLabel' => fn ($m) => $m->sender_id === auth()->id() ? __('You') : $conversation->store->name,
        ])
    </div>
</x-layouts.store>
