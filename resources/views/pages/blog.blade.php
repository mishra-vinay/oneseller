<x-layouts.store :title="__('Blog')" :canonical="route('blog')">
    <h1 class="mb-6 text-2xl font-semibold">{{ __('Blog') }}</h1>
    @if ($posts->isEmpty())
        <p class="rounded-lg border border-stone-200 bg-white p-8 text-center text-sm text-stone-500">{{ __('No posts yet.') }}</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <a href="{{ $post->url() }}" class="group overflow-hidden rounded-lg border border-stone-200 bg-white hover:border-stone-400">
                    <div class="aspect-[16/9] bg-stone-100">
                        @if ($post->coverUrl()) <img src="{{ $post->coverUrl() }}" alt="" loading="lazy" class="h-full w-full object-cover"> @endif
                    </div>
                    <div class="p-4">
                        <h2 class="font-semibold group-hover:underline">{{ $post->title }}</h2>
                        <p class="mt-1 text-xs text-stone-500">{{ $post->published_at?->format('d M Y') }}</p>
                        @if ($post->excerpt) <p class="mt-2 text-sm text-stone-600">{{ $post->excerpt }}</p> @endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $posts->links() }}</div>
    @endif
</x-layouts.store>
