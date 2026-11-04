<?php

namespace App\Http\Controllers\Seller;

use App\Enums\Role;
use App\Models\Conversation;
use App\Services\Messaging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends SellerController
{
    public function __construct(private Messaging $messaging) {}

    public function index(Request $request): View
    {
        return view('messages.seller-index', [
            'conversations' => $this->query($request)->with('buyer')->orderByDesc('last_message_at')->paginate(25),
        ]);
    }

    public function show(Request $request, int $conversation): View
    {
        $model = $this->query($request)->findOrFail($conversation);
        $this->messaging->markRead($model, Role::Seller);

        return view('messages.seller-show', ['conversation' => $model->load(['buyer', 'messages.sender', 'messages.product', 'messages.subOrder'])]);
    }

    public function reply(Request $request, int $conversation): RedirectResponse
    {
        $this->messaging->reply($this->query($request)->findOrFail($conversation), $request->user(), $request->validate(['body' => ['required', 'string', 'max:2000']])['body']);

        return back();
    }

    private function query(Request $request)
    {
        return Conversation::where('store_id', $this->currentStore($request)->id);
    }
}
