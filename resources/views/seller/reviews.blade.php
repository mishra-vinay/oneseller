<x-layouts.panel :title="__('Reviews')" area="seller">
    <div class="space-y-3">
        @forelse ($reviews as $review)
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span><x-stars :rating="$review->rating" /> <span class="font-medium">{{ $review->product->name }}</span></span>
                    <span class="text-stone-500">{{ $review->authorName() }} · {{ $review->created_at->format('d M Y') }} @if ($review->is_hidden) · <x-badge status="disabled" /> @endif</span>
                </div>
                @if ($review->title) <p class="mt-2 font-medium">{{ $review->title }}</p> @endif
                @if ($review->body) <p class="mt-1 whitespace-pre-line text-sm text-stone-700">{{ $review->body }}</p> @endif
                <form method="POST" action="{{ route('seller.reviews.reply', $review->id) }}" class="mt-3 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="min-w-64 flex-1">
                        <label for="reply-{{ $review->id }}" class="mb-1 block text-sm font-medium">{{ __('Your public reply') }}</label>
                        <input id="reply-{{ $review->id }}" name="reply" value="{{ $review->seller_reply }}" maxlength="2000" class="block w-full rounded-md border border-stone-300 px-3 py-2 text-sm">
                    </div>
                    <button class="rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium hover:bg-stone-50">{{ $review->seller_reply ? __('Update reply') : __('Reply') }}</button>
                </form>
            </x-card>
        @empty
            <x-card><p class="text-sm text-stone-500">{{ __('No reviews yet. Buyers can review products once they are delivered.') }}</p></x-card>
        @endforelse
    </div>
    <div class="mt-4">{{ $reviews->links() }}</div>
</x-layouts.panel>
