<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment_status->value,
            'items_total' => $this->items_total,
            'shipping_total' => $this->shipping_total,
            'coupon_code' => $this->coupon_code,
            'discount_total' => $this->discount_total,
            'grand_total' => $this->grand_total,
            'refunded_total' => $this->refunded_total,
            'shipping_address' => [
                'name' => $this->ship_name,
                'phone' => $this->ship_phone,
                'address_line1' => $this->ship_address_line1,
                'address_line2' => $this->ship_address_line2,
                'city' => $this->ship_city,
                'state' => $this->ship_state,
                'pincode' => $this->ship_pincode,
            ],
            'parcels' => SubOrderResource::collection($this->whenLoaded('subOrders')),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
