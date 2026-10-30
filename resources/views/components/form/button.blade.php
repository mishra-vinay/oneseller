<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex justify-center rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700 focus:outline-none focus:ring-2 focus:ring-stone-900 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
