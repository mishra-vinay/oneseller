<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\DB;

class Messaging
{
    /**
     * A buyer writes to a store, optionally about a product or one of their parcels.
     */
    public function buyerWrites(User $buyer, Store $store, string $body, ?Product $product = null, ?SubOrder $subOrder = null): Message
    {
        abort_unless($store->isApproved(), 404);
        abort_if($product && $product->store_id !== $store->id, 404);
        abort_if($subOrder && ($subOrder->store_id !== $store->id || $subOrder->order->buyer_id !== $buyer->id), 404);

        $conversation = Conversation::firstOrCreate(['buyer_id' => $buyer->id, 'store_id' => $store->id]);

        return $this->send($conversation, $buyer, $body, $product, $subOrder);
    }

    public function reply(Conversation $conversation, User $sender, string $body): Message
    {
        return $this->send($conversation, $sender, $body);
    }

    public function markRead(Conversation $conversation, Role $role): void
    {
        $conversation->forceFill([$role === Role::Seller ? 'seller_read_at' : 'buyer_read_at' => now()])->save();
    }

    private function send(Conversation $conversation, User $sender, string $body, ?Product $product = null, ?SubOrder $subOrder = null): Message
    {
        $senderRole = $sender->role === Role::Seller ? Role::Seller : Role::Buyer;
        $recipientRole = $senderRole === Role::Seller ? Role::Buyer : Role::Seller;

        // Only email the other side if they had caught up; otherwise they already have unread messages waiting.
        $shouldNotify = ! $conversation->unreadFor($recipientRole);

        $message = DB::transaction(function () use ($conversation, $sender, $senderRole, $body, $product, $subOrder) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => $body,
                'product_id' => $product?->id,
                'sub_order_id' => $subOrder?->id,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                $senderRole === Role::Seller ? 'seller_read_at' : 'buyer_read_at' => $message->created_at,
            ])->save();

            return $message;
        });

        if ($shouldNotify) {
            $recipient = $recipientRole === Role::Seller ? $conversation->store->user : $conversation->buyer;
            $recipient->notify(new NewMessage($message));
        }

        return $message;
    }
}
