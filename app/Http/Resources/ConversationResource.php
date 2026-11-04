<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewerIsSeller = $request->user()?->role === Role::Seller;

        return [
            'id' => $this->id,
            // The other party: the store for a buyer, the buyer's name for a seller.
            'with' => $viewerIsSeller ? $this->buyer?->name : $this->store?->name,
            'store_slug' => $this->store?->slug,
            'unread' => $this->unreadFor($viewerIsSeller ? Role::Seller : Role::Buyer),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'mine' => $m->sender_id === $request->user()->id,
                'product_id' => $m->product_id,
                'parcel_number' => $m->subOrder?->number,
                'created_at' => $m->created_at->toIso8601String(),
            ])),
        ];
    }
}
