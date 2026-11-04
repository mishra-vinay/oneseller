@if ($errors->any())
    <div role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ __('Please fix the highlighted fields.') }}
        @if ($errors->has('target') || $errors->has('ids') || $errors->has('reason'))
            <ul class="mt-1 list-disc pl-5">
                @foreach (['target', 'ids', 'reason'] as $key)
                    @foreach ($errors->get($key) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </div>
@endif
