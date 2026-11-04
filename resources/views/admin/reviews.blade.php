<x-layouts.panel :title="__('Reviews')" area="admin">
    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <select name="rating" class="rounded-md border border-stone-300 bg-white px-3 py-2" onchange="this.form.submit()">
            <option value="">{{ __('All ratings') }}</option>
            @foreach (range(1, 5) as $r) <option value="{{ $r }}" @selected((int) request('rating') === $r)>{{ str_repeat('★', $r) }}</option> @endforeach
        </select>
        <label class="flex items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-2"><input type="checkbox" name="hidden" value="1" @checked(request()->boolean('hidden')) onchange="this.form.submit()"> {{ __('Hidden only') }}</label>
    </form>
    <div class="space-y-3">
        @forelse ($reviews as $review)
            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span><x-stars :rating="$review->rating" /> <span class="font-medium">{{ $review->product->name }}</span> · {{ $review->product->store->name }}</span>
                    <form method="POST" action="{{ route('admin.reviews.toggle', $review) }}">
                        @csrf
                        <button class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm hover:bg-stone-50">{{ $review->is_hidden ? __('Show again') : __('Hide') }}</button>
                    </form>
                </div>
                <p class="mt-1 text-xs text-stone-500">{{ $review->user->name }} ({{ $review->user->email }}) · {{ $review->created_at->format('d M Y') }} @if ($review->is_hidden) · <x-badge status="disabled" /> @endif</p>
                @if ($review->title) <p class="mt-2 font-medium">{{ $review->title }}</p> @endif
                @if ($review->body) <p class="mt-1 whitespace-pre-line text-sm text-stone-700">{{ $review->body }}</p> @endif
                @if ($review->seller_reply) <p class="mt-2 rounded bg-stone-100 px-3 py-2 text-sm"><span class="font-medium">{{ __('Seller reply:') }}</span> {{ $review->seller_reply }}</p> @endif
            </x-card>
        @empty
            <x-card><p class="text-sm text-stone-500">{{ __('No reviews.') }}</p></x-card>
        @endforelse
    </div>
    <div class="mt-4">{{ $reviews->links() }}</div>
</x-layouts.panel>
