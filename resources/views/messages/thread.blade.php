{{-- Shared message list. $viewerId is the signed-in user; $replyAction is null for read-only views. --}}
<div class="space-y-3">
    @foreach ($conversation->messages as $message)
        @php $mine = $message->sender_id === $viewerId; @endphp
        <div @class(['flex', 'justify-end' => $mine])>
            <div @class(['max-w-[80%] rounded-lg px-4 py-2 text-sm', 'bg-stone-900 text-white' => $mine, 'border border-stone-200 bg-white' => ! $mine])>
                @if ($message->product || $message->subOrder)
                    <div @class(['mb-1 text-xs', 'text-stone-300' => $mine, 'text-stone-500' => ! $mine])>
                        {{ __('About') }} {{ $message->product?->name ?? __('parcel :number', ['number' => $message->subOrder?->number]) }}
                    </div>
                @endif
                <p class="whitespace-pre-line">{{ $message->body }}</p>
                <div @class(['mt-1 text-xs', 'text-stone-300' => $mine, 'text-stone-500' => ! $mine])>{{ $senderLabel($message) }} · {{ $message->created_at->format('d M, g:i A') }}</div>
            </div>
        </div>
    @endforeach
</div>
@if ($replyAction)
    <form method="POST" action="{{ $replyAction }}" class="mt-6 space-y-2">
        @csrf
        <x-form.textarea name="body" :label="__('Reply')" rows="3" required maxlength="2000" />
        <x-form.button>{{ __('Send') }}</x-form.button>
        <p class="text-xs text-stone-500">{{ __('Never share card, UPI or bank details in messages.') }}</p>
    </form>
@endif
