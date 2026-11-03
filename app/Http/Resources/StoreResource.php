<?php

namespace App\Http\Resources;

use App\Models\Store;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Store */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo_url' => $this->logoUrl(),
            'city' => $this->city,
            'state' => $this->state,
            'status' => $this->status->value,
            'shipping' => app(Pricing::class)->describe($this->resource),
            'url' => route('store.show', $this->resource),
        ];
    }
}
