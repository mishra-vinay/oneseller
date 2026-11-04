<x-layouts.panel :title="__('Messages with :name', ['name' => $conversation->buyer->name])" area="seller">
    <div class="max-w-2xl">
        @include('messages.thread', [
            'viewerId' => auth()->id(),
            'replyAction' => route('seller.messages.reply', $conversation->id),
            'senderLabel' => fn ($m) => $m->sender_id === auth()->id() ? __('You') : $conversation->buyer->name,
        ])
    </div>
</x-layouts.panel>
