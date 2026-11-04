@props(['stores', 'category' => null, 'activeSub' => null])
@php use App\Services\Catalog; @endphp
<form method="GET" class="space-y-5 text-sm">
    @if (request('q') !== null)
        <input type="hidden" name="q" value="{{ request('q') }}">
    @endif

    @if ($category && $category->children->isNotEmpty())
        <div>
            <div class="mb-2 font-medium">{{ __('Sub-category') }}</div>
            <ul class="space-y-1">
                <li><a href="{{ request()->fullUrlWithQuery(['sub' => null, 'page' => null]) }}" @class(['hover:underline', 'font-semibold' => ! $activeSub])>{{ __('All :name', ['name' => $category->name]) }}</a></li>
                @foreach ($category->children as $child)
                    <li><a href="{{ request()->fullUrlWithQuery(['sub' => $child->slug, 'page' => null]) }}" @class(['hover:underline', 'font-semibold' => $activeSub?->is($child)])>{{ $child->name }}</a></li>
                @endforeach
            </ul>
            @if ($activeSub) <input type="hidden" name="sub" value="{{ $activeSub->slug }}"> @endif
        </div>
    @endif

    <div>
        <div class="mb-2 font-medium">{{ __('Price (₹)') }}</div>
        <div class="flex items-center gap-2">
            <input name="min" value="{{ request('min') }}" inputmode="numeric" placeholder="{{ __('Min') }}" aria-label="{{ __('Minimum price') }}" class="w-full rounded-md border border-stone-300 px-2 py-1.5">
            <span>–</span>
            <input name="max" value="{{ request('max') }}" inputmode="numeric" placeholder="{{ __('Max') }}" aria-label="{{ __('Maximum price') }}" class="w-full rounded-md border border-stone-300 px-2 py-1.5">
        </div>
    </div>

    @if ($stores->count() > 1)
        <div>
            <label for="seller" class="mb-2 block font-medium">{{ __('Seller') }}</label>
            <select id="seller" name="seller" class="w-full rounded-md border border-stone-300 bg-white px-2 py-1.5">
                <option value="">{{ __('All sellers') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->slug }}" @selected(request('seller') === $store->slug)>{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <label class="flex items-center gap-2">
        <input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))> {{ __('In stock only') }}
    </label>

    <div>
        <label for="sort" class="mb-2 block font-medium">{{ __('Sort by') }}</label>
        <select id="sort" name="sort" class="w-full rounded-md border border-stone-300 bg-white px-2 py-1.5">
            @foreach (Catalog::SORTS as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>

    <button class="w-full rounded-md bg-stone-900 px-3 py-2 font-medium text-white hover:bg-stone-700">{{ __('Apply filters') }}</button>
</form>
