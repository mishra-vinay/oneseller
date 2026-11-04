<x-layouts.panel :title="$conversation->buyer->name.' ↔ '.$conversation->store->name" area="admin">
    <div class="max-w-2xl">
        @include('messages.thread', [
            'viewerId' => $conversation->store->user_id,
            'replyAction' => null,
            'senderLabel' => fn ($m) => $m->sender_id === $conversation->buyer_id ? $conversation->buyer->name.' ('.__('buyer').')' : $conversation->store->name,
        ])
    </div>
</x-layouts.panel>
