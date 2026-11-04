<?php

namespace App\Http\Resources;

use App\Models\SubOrder;
use App\Services\Returns;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A seller's parcel as the buyer sees it.
 *
 * @mixin SubOrder
 */
class SubOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'store' => new StoreResource($this->whenLoaded('store')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'items_subtotal' => $this->items_subtotal,
            'shipping' => $this->shipping,
            'discount' => $this->discount,
            'total' => $this->total(),
            'courier' => $this->courier,
            'tracking_number' => $this->tracking_number,
            'cancel_reason' => $this->cancel_reason,
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'can_return' => app(Returns::class)->windowOpen($this->resource),
        ];
    }
}
