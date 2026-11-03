<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only view of buyer–seller conversations, for resolving disputes.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        return view('messages.admin-index', [
            'conversations' => Conversation::with(['buyer', 'store'])
                ->when($request->query('store'), fn ($q, $slug) => $q->whereHas('store', fn ($s) => $s->where('slug', $slug)))
                ->orderByDesc('last_message_at')->paginate(30)->withQueryString(),
        ]);
    }

    public function show(Conversation $conversation): View
    {
        return view('messages.admin-show', ['conversation' => $conversation->load(['buyer', 'store', 'messages.sender', 'messages.product', 'messages.subOrder'])]);
    }
}
