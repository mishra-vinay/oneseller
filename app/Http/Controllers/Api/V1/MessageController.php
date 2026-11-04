<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Services\Messaging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Conversations for whoever is signed in: a buyer's stores, or a seller's buyers.
 */
class MessageController extends Controller
{
    public function __construct(private Messaging $messaging) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ConversationResource::collection($this->query($request)->with(['buyer', 'store'])->orderByDesc('last_message_at')->paginate(25));
    }

    public function show(Request $request, int $conversation): ConversationResource
    {
        $model = $this->query($request)->findOrFail($conversation);
        $this->messaging->markRead($model, $this->role($request));

        return new ConversationResource($model->load(['buyer', 'store', 'messages.subOrder']));
    }

    public function reply(Request $request, int $conversation): ConversationResource
    {
        $model = $this->query($request)->findOrFail($conversation);
        $this->messaging->reply($model, $request->user(), $request->validate(['body' => ['required', 'string', 'max:2000']])['body']);

        return new ConversationResource($model->fresh()->load(['buyer', 'store', 'messages.subOrder']));
    }

    public function start(Request $request, Store $store): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer'],
            'parcel_id' => ['nullable', 'integer'],
        ]);

        $message = $this->messaging->buyerWrites(
            $request->user(),
            $store,
            $data['body'],
            filled($data['product_id'] ?? null) ? Product::findOrFail($data['product_id']) : null,
            filled($data['parcel_id'] ?? null) ? SubOrder::with('order')->findOrFail($data['parcel_id']) : null,
        );

        return (new ConversationResource($message->conversation->load(['buyer', 'store', 'messages.subOrder'])))->response()->setStatusCode(201);
    }

    private function role(Request $request): Role
    {
        return $request->user()->role === Role::Seller ? Role::Seller : Role::Buyer;
    }

    private function query(Request $request)
    {
        return $this->role($request) === Role::Seller
            ? Conversation::where('store_id', $request->user()->store?->id)
            : Conversation::where('buyer_id', $request->user()->id);
    }
}
