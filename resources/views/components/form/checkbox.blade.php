@props(['name', 'label', 'checked' => false, 'hint' => null])
<label class="flex items-start gap-3">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="mt-1 rounded border-stone-300">
    <span>
        <span class="block text-sm font-medium">{{ $label }}</span>
        @if ($hint)
            <span class="block text-xs text-stone-500">{{ $hint }}</span>
        @endif
    </span>
</label>
