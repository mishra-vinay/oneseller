@php
    use App\Enums\ProductStatus;
    use App\Support\Money;
    $variant = $product->defaultVariant;
    $editing = $product->exists;
@endphp
<x-layouts.panel :title="$editing ? $product->name : __('Add product')" area="seller">
    @if ($editing)
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
            <x-badge :status="$product->status" />
            @if ($product->admin_disabled) <x-badge status="disabled" /> <span class="text-stone-600">{{ __('Deactivated by the marketplace team.') }}</span> @endif
            @if ($product->status === ProductStatus::Approved && $product->isVisible())
                <a href="{{ $product->url() }}" class="text-stone-600 hover:underline" target="_blank">{{ __('View on storefront') }} ↗</a>
            @endif
        </div>
        @if ($product->status === ProductStatus::Rejected)
            <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                <span class="font-medium">{{ __('Rejected:') }}</span> {{ $product->rejection_reason }}
                <div class="mt-1 text-red-800">{{ __('Correct it and press "Submit for approval" again.') }}</div>
            </div>
        @elseif ($product->status === ProductStatus::Approved)
            <p class="mb-4 text-sm text-stone-600">{{ __('Changing the name, descriptions, options, prices or images sends this product back for review. Stock and publishing do not.') }}</p>
        @endif
    @endif

    <form method="POST" action="{{ $editing ? route('seller.products.update', $product->id) : route('seller.products.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <div class="space-y-4">
                    <x-form.input name="name" :label="__('Product name')" :value="$product->name" required maxlength="200" />
                    <x-form.select name="category_id" :label="__('Category')" required>
                        <option value="">{{ __('Choose a category') }}</option>
                        @foreach ($categories as $parent)
                            <option value="{{ $parent->id }}" @selected((string) old('category_id', $product->category_id) === (string) $parent->id)>{{ $parent->name }}</option>
                            @foreach ($parent->children as $child)
                                <option value="{{ $child->id }}" @selected((string) old('category_id', $product->category_id) === (string) $child->id)>&nbsp;&nbsp;{{ $parent->name }} › {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </x-form.select>
                    <x-form.textarea name="short_description" :label="__('Short description')" :value="$product->short_description" rows="2" maxlength="500" :hint="__('One or two lines shown near the price.')" />
                    <x-form.textarea name="description" :label="__('Full description')" :value="$product->description" rows="8" />
                </div>
            </x-card>

            <x-card :title="__('Options (size, colour…)')">
                <p class="mb-3 text-sm text-stone-600">{{ __('Optional. Add up to two options with comma-separated values, e.g. Size: S, M, L. Each combination becomes a variant with its own SKU, price and stock.') }}</p>
                @php $savedOptions = collect($product->option_names ?? [])->map(fn ($name) => ['name' => $name, 'values' => $product->variants->pluck('options.'.$name)->filter()->unique()->implode(', ')]); @endphp
                <div class="grid gap-3 sm:grid-cols-[10rem_1fr]">
                    @foreach (range(0, \App\Services\ProductService::MAX_OPTIONS - 1) as $i)
                        <x-form.input :name="'options['.$i.'][name]'" :label="__('Option :n name', ['n' => $i + 1])" :value="old('options.'.$i.'.name', $savedOptions[$i]['name'] ?? '')" :placeholder="$i === 0 ? __('Size') : __('Colour')" />
                        <x-form.input :name="'options['.$i.'][values]'" :label="__('Values')" :value="old('options.'.$i.'.values', $savedOptions[$i]['values'] ?? '')" :placeholder="$i === 0 ? 'S, M, L' : 'Red, Blue'" />
                    @endforeach
                </div>
                @error('options') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                @if (! $product->hasOptions())
                    <p class="mt-3 text-xs text-stone-500">{{ __('After saving, you can set the price and stock of each combination.') }}</p>
                @endif
            </x-card>

            @if ($product->hasOptions())
                <x-card :title="__('Variants')">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase text-stone-500">
                                <tr>
                                    <th class="py-2 pr-2">{{ __('Variant') }}</th>
                                    <th class="py-2 pr-2">{{ __('SKU') }}</th>
                                    <th class="py-2 pr-2">{{ __('Price (₹)') }}</th>
                                    <th class="py-2 pr-2">{{ __('Compare-at (₹)') }}</th>
                                    <th class="py-2 pr-2">{{ __('Stock') }}</th>
                                    <th class="py-2">{{ __('Weight (g)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach ($product->variants as $v)
                                    <tr>
                                        <td class="py-2 pr-2 font-medium whitespace-nowrap">{{ $v->label() }}</td>
                                        <td class="py-2 pr-2"><input name="variants[{{ $v->id }}][sku]" value="{{ old("variants.{$v->id}.sku", $v->sku) }}" required maxlength="64" aria-label="{{ __('SKU for :v', ['v' => $v->label()]) }}" class="w-32 rounded-md border border-stone-300 px-2 py-1"></td>
                                        <td class="py-2 pr-2"><input name="variants[{{ $v->id }}][price]" value="{{ old("variants.{$v->id}.price", Money::toRupees($v->price)) }}" required inputmode="decimal" aria-label="{{ __('Price for :v', ['v' => $v->label()]) }}" class="w-24 rounded-md border border-stone-300 px-2 py-1"></td>
                                        <td class="py-2 pr-2"><input name="variants[{{ $v->id }}][compare_at_price]" value="{{ old("variants.{$v->id}.compare_at_price", $v->compare_at_price ? Money::toRupees($v->compare_at_price) : '') }}" inputmode="decimal" aria-label="{{ __('Compare-at price for :v', ['v' => $v->label()]) }}" class="w-24 rounded-md border border-stone-300 px-2 py-1"></td>
                                        <td class="py-2 pr-2"><input name="variants[{{ $v->id }}][stock]" type="number" min="0" value="{{ old("variants.{$v->id}.stock", $v->stock) }}" required aria-label="{{ __('Stock for :v', ['v' => $v->label()]) }}" class="w-20 rounded-md border border-stone-300 px-2 py-1"></td>
                                        <td class="py-2"><input name="variants[{{ $v->id }}][weight_grams]" type="number" min="0" value="{{ old("variants.{$v->id}.weight_grams", $v->weight_grams) }}" aria-label="{{ __('Weight for :v', ['v' => $v->label()]) }}" class="w-20 rounded-md border border-stone-300 px-2 py-1"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($errors->has('variants.*'))
                        <p class="mt-2 text-sm text-red-600">{{ __('Check the highlighted variant rows: every variant needs a SKU, a price in rupees and a stock count.') }}</p>
                    @endif
                    <p class="mt-2 text-xs text-stone-500">{{ __('New option values added above start with zero stock.') }}</p>
                </x-card>
            @endif

            <x-card :title="__('Images (up to :max)', ['max' => \App\Models\Product::MAX_IMAGES])">
                @if ($editing && $product->images->isNotEmpty())
                    <div class="mb-4 grid grid-cols-3 gap-3 sm:grid-cols-5">
                        @foreach ($product->images as $image)
                            <label class="block text-xs">
                                <img src="{{ $image->url() }}" alt="" class="aspect-square w-full rounded-md border border-stone-200 object-cover">
                                <span class="mt-1 flex items-center gap-1"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}"> {{ __('Remove') }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" class="text-sm">
                <p class="mt-1 text-xs text-stone-500">{{ __('JPG, PNG or WebP, up to 4 MB each. The first image is the main photo.') }}</p>
                @foreach (['images', 'images.0', 'images.1', 'images.2', 'images.3', 'images.4'] as $key)
                    @error($key) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @endforeach
            </x-card>
        </div>

        <div class="space-y-6">
            @unless ($product->hasOptions())
            <x-card :title="__('Price and stock')">
                    <div class="space-y-4">
                        <x-form.input name="price" :label="__('Price (₹, incl. GST)')" :value="$variant ? Money::toRupees($variant->price) : null" required inputmode="decimal" />
                        <x-form.input name="compare_at_price" :label="__('Compare-at price (₹, optional)')" :value="$variant && $variant->compare_at_price ? Money::toRupees($variant->compare_at_price) : null" inputmode="decimal" :hint="__('Shown struck through when higher than the price.')" />
                        <x-form.input name="stock" type="number" min="0" :label="__('Stock')" :value="$variant?->stock ?? 0" required />
                        <x-form.input name="sku" :label="__('SKU')" :value="$variant?->sku" required maxlength="64" />
                        <x-form.input name="weight_grams" type="number" min="0" :label="__('Weight (grams)')" :value="$variant?->weight_grams" />
                    </div>
                </x-card>
            @endunless

            <x-card>
                <x-form.checkbox name="is_published" :label="__('Published')" :checked="$product->is_published ?? true" :hint="__('Untick to hide it from the storefront without deleting it.')" />
                <div class="mt-5 flex flex-col gap-2">
                    @if (! $editing || in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected], true))
                        <x-form.button name="action" value="submit">{{ __('Submit for approval') }}</x-form.button>
                        <button type="submit" name="action" value="draft" class="rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium hover:bg-stone-50">{{ __('Save as draft') }}</button>
                    @else
                        <x-form.button name="action" value="save">{{ __('Save changes') }}</x-form.button>
                    @endif
                </div>
            </x-card>
        </div>
    </form>

    @if ($editing)
        <form method="POST" action="{{ route('seller.products.destroy', $product->id) }}" class="mt-6" onsubmit="return confirm('{{ __('Delete this product? This cannot be undone.') }}')">
            @csrf
            @method('DELETE')
            <button class="text-sm text-red-700 hover:underline">{{ __('Delete product') }}</button>
        </form>
    @endif
</x-layouts.panel>
