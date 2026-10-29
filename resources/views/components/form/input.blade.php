@props(['name', 'label', 'type' => 'text', 'value' => null, 'bag' => 'default', 'hint' => null])
<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           {{ $attributes->class(['block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-stone-900', 'border-red-400' => $errors->getBag($bag)->has($name), 'border-stone-300' => ! $errors->getBag($bag)->has($name)]) }}>
    @if ($hint)
        <p class="mt-1 text-xs text-stone-500">{{ $hint }}</p>
    @endif
    @foreach ($errors->getBag($bag)->get($name) as $message)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @endforeach
</div>
