<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class SellerProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'is_published' => $this->is_published,
            'admin_disabled' => $this->admin_disabled,
            'option_names' => $this->option_names ?? [],
            'variants' => VariantResource::collection($this->variants),
            'images' => $this->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url()])->values(),
            'rating' => ['average' => $this->rating_avg, 'count' => $this->rating_count],
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
