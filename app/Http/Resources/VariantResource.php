<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductVariant */
class VariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'options' => $this->options,
            'label' => $this->label(),
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'stock' => $this->stock,
            'in_stock' => $this->stock > 0,
            'weight_grams' => $this->weight_grams,
            'is_default' => $this->is_default,
        ];
    }
}
