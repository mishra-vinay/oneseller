<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storefront product. List responses carry the summary; detail adds description and images.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public bool $detail = false;

    public function detailed(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $default = $this->defaultVariant;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'price' => $default?->price,
            'compare_at_price' => $default?->compare_at_price,
            'has_price_range' => $this->hasPriceRange(),
            'in_stock' => $this->totalStock() > 0,
            'rating' => ['average' => $this->rating_avg, 'count' => $this->rating_count],
            'image_url' => $this->primaryImageUrl(),
            'store' => new StoreResource($this->whenLoaded('store')),
            'url' => $this->url(),
            $this->mergeWhen($this->detail, fn () => [
                'description' => $this->description,
                'option_names' => $this->option_names ?? [],
                'variants' => VariantResource::collection($this->variants),
                'images' => $this->images->map(fn ($i) => $i->url())->values(),
                'category' => new CategoryResource($this->category),
            ]),
        ];
    }
}
