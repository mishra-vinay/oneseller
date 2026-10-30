@props(['title'])
<div class="mx-auto mt-6 max-w-md rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
    <h1 class="mb-6 text-xl font-semibold">{{ $title }}</h1>
    {{ $slot }}
</div>
