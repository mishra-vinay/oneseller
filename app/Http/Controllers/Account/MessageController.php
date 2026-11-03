<?php

namespace App\Http\Controllers\Account;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\Store;
use App\Models\SubOrder;
use App\Services\Messaging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function __construct(private Messaging $messaging) {}

    public function index(Request $request): View
    {
        return view('messages.buyer-index', [
            'conversations' => Conversation::where('buyer_id', $request->user()->id)->with('store')->orderByDesc('last_message_at')->paginate(20),
        ]);
    }

    public function show(Request $request, int $conversation): View
    {
        $model = $this->find($request, $conversation);
        $this->messaging->markRead($model, Role::Buyer);

        return view('messages.buyer-show', ['conversation' => $model->load(['store', 'messages.sender', 'messages.product', 'messages.subOrder'])]);
    }

    public function compose(Request $request, Store $store): View
    {
        $subOrder = $request->integer('sub_order_id')
            ? SubOrder::where('store_id', $store->id)->whereHas('order', fn ($q) => $q->where('buyer_id', $request->user()->id))->findOrFail($request->integer('sub_order_id'))
            : null;

        return view('messages.compose', ['store' => $store, 'subOrder' => $subOrder]);
    }

    public function start(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer'],
            'sub_order_id' => ['nullable', 'integer'],
        ]);

        $message = $this->messaging->buyerWrites(
            $request->user(),
            $store,
            $data['body'],
            filled($data['product_id'] ?? null) ? Product::findOrFail($data['product_id']) : null,
            filled($data['sub_order_id'] ?? null) ? SubOrder::with('order')->findOrFail($data['sub_order_id']) : null,
        );

        return redirect()->route('account.messages.show', $message->conversation_id)->with('status', __('Message sent to :store.', ['store' => $store->name]));
    }

    public function reply(Request $request, int $conversation): RedirectResponse
    {
        $this->messaging->reply($this->find($request, $conversation), $request->user(), $request->validate(['body' => ['required', 'string', 'max:2000']])['body']);

        return back();
    }

    private function find(Request $request, int $id): Conversation
    {
        return Conversation::where('buyer_id', $request->user()->id)->findOrFail($id);
    }
}
