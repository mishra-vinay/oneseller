<x-layouts.store :title="$page->title" :description="$page->excerpt" :canonical="$page->url()">
    @push('head')
        @if ($page->coverUrl()) <meta property="og:image" content="{{ $page->coverUrl() }}"> @endif
    @endpush
    <article class="mx-auto max-w-3xl">
        <a href="{{ route('blog') }}" class="text-sm text-stone-600 hover:underline">← {{ __('Blog') }}</a>
        <h1 class="mt-2 text-3xl font-semibold">{{ $page->title }}</h1>
        <p class="mt-1 text-sm text-stone-500">{{ $page->published_at?->format('d M Y') }}</p>
        @if ($page->coverUrl())
            <img src="{{ $page->coverUrl() }}" alt="" class="mt-6 w-full rounded-lg object-cover">
        @endif
        <div class="prose-content mt-6 rounded-lg border border-stone-200 bg-white p-6 sm:p-10">{{ $page->html() }}</div>
    </article>
</x-layouts.store>
