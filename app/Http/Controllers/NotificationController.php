<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * In-panel notifications for sellers and admins.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications', [
            'area' => $request->user()->role->value,
            'notifications' => $request->user()->notifications()->paginate(30),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? url()->previous());
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', __('All notifications marked as read.'));
    }
}
