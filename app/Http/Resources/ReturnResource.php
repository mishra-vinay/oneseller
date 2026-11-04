<?php

namespace App\Http\Resources;

use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReturnRequest */
class ReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'reason_label' => $this->reasonLabel(),
            'details' => $this->details,
            'seller_note' => $this->seller_note,
            'refund_amount' => $this->refund_amount,
            'parcel_number' => $this->subOrder?->number,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($line) => [
                'order_item_id' => $line->order_item_id,
                'name' => $line->orderItem->displayName(),
                'quantity' => $line->quantity,
            ])),
            'replacement_courier' => $this->replacement_courier,
            'replacement_tracking' => $this->replacement_tracking,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
