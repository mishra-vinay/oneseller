@props(['stores', 'category' => null, 'activeSub' => null, 'idPrefix' => ''])
@php use App\Services\Catalog; @endphp
<form method="GET" class="space-y-5 text-sm">
    @if (request('q') !== null)
        <input type="hidden" name="q" value="{{ request('q') }}">
    @endif
    @if ($activeSub) <input type="hidden" name="sub" value="{{ $activeSub->slug }}"> @endif

    @if ($category && $category->children->isNotEmpty())
        <div>
            <div class="mb-2 font-semibold">{{ __('Sub-category') }}</div>
            <ul class="space-y-0.5">
                <li><a href="{{ request()->fullUrlWithQuery(['sub' => null, 'page' => null]) }}" @class(['flex min-h-8 items-center hover:text-brand-600', 'font-bold text-brand-600' => ! $activeSub])>{{ __('All :name', ['name' => $category->name]) }}</a></li>
                @foreach ($category->children as $child)
                    <li><a href="{{ request()->fullUrlWithQuery(['sub' => $child->slug, 'page' => null]) }}" @class(['flex min-h-8 items-center hover:text-brand-600', 'font-bold text-brand-600' => $activeSub?->is($child)])>{{ $child->name }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <fieldset>
        <legend class="mb-2 font-semibold">{{ __('Price (₹)') }}</legend>
        <div class="flex items-center gap-2">
            <label for="{{ $idPrefix }}min" class="sr-only">{{ __('Minimum price') }}</label>
            <input id="{{ $idPrefix }}min" name="min" value="{{ request('min') }}" inputmode="numeric" placeholder="{{ __('Min') }}" class="field">
            <span aria-hidden="true">–</span>
            <label for="{{ $idPrefix }}max" class="sr-only">{{ __('Maximum price') }}</label>
            <input id="{{ $idPrefix }}max" name="max" value="{{ request('max') }}" inputmode="numeric" placeholder="{{ __('Max') }}" class="field">
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach ([[null, 500], [500, 1000], [1000, 2500], [2500, null]] as [$lo, $hi])
                <a href="{{ request()->fullUrlWithQuery(['min' => $lo, 'max' => $hi, 'page' => null]) }}" rel="nofollow"
                   @class(['rounded-full border px-2.5 py-1 text-xs', 'border-brand-600 bg-brand-50 font-semibold text-brand-600' => request('min') == $lo && request('max') == $hi && ($lo || $hi), 'border-stone-300 hover:border-stone-500' => ! (request('min') == $lo && request('max') == $hi)])>
                    {{ $lo && $hi ? '₹'.number_format($lo).' – ₹'.number_format($hi) : ($hi ? __('Under ₹:n', ['n' => number_format($hi)]) : __('Over ₹:n', ['n' => number_format($lo)])) }}
                </a>
            @endforeach
        </div>
    </fieldset>

    @if ($stores->count() > 1)
        <div>
            <label for="{{ $idPrefix }}seller" class="mb-2 block font-semibold">{{ __('Seller') }}</label>
            <select id="{{ $idPrefix }}seller" name="seller" class="field">
                <option value="">{{ __('All sellers') }}</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->slug }}" @selected(request('seller') === $store->slug)>{{ $store->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <label class="flex min-h-8 cursor-pointer items-center gap-2.5">
        <input type="checkbox" name="in_stock" value="1" class="h-4.5 w-4.5 accent-brand-600" @checked(request()->boolean('in_stock'))> {{ __('In stock only') }}
    </label>

    <div>
        <label for="{{ $idPrefix }}sort" class="mb-2 block font-semibold">{{ __('Sort by') }}</label>
        <select id="{{ $idPrefix }}sort" name="sort" class="field">
            @foreach (Catalog::SORTS as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>

    <button class="btn-dark w-full">{{ __('Apply filters') }}</button>
</form>
