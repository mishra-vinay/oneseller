<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * Sends admin alerts to every active admin, plus the order notification
 * email from settings when it is not already an admin's address.
 */
class AdminNotifier
{
    public static function send(Notification $notification): void
    {
        $admins = User::query()->role(Role::Admin)->active()->get();

        Notifier::send($admins, $notification);

        $extra = app(Settings::class)->get('order_notification_email');

        if ($extra && ! $admins->contains('email', $extra)) {
            Notifier::route('mail', $extra)->notify($notification);
        }
    }
}
