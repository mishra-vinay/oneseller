<x-layouts.store :title="$title">
    <article class="mx-auto max-w-3xl rounded-lg border border-stone-200 bg-white p-6 sm:p-10">
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>
        <p class="mt-1 text-sm text-amber-800">{{ __('Template text: have this reviewed by a lawyer and adapted to your business before launch.') }}</p>
        <div class="mt-6 space-y-4 text-stone-700 [&_h2]:mt-6 [&_h2]:font-semibold [&_h2]:text-stone-900">
            {{ $slot }}
        </div>
    </article>
</x-layouts.store>
