<x-layouts.store :title="$page->title" :description="$page->excerpt" :canonical="$page->url()">
    <article class="mx-auto max-w-3xl rounded-lg border border-stone-200 bg-white p-6 sm:p-10">
        <h1 class="text-2xl font-semibold">{{ $page->title }}</h1>
        <div class="prose-content mt-6">{{ $page->html() }}</div>
    </article>
</x-layouts.store>
