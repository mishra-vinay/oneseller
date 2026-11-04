<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Push\FcmClient;
use Illuminate\Notifications\Notification;

/**
 * Sends a notification's toPush() to every device the user signed in on. Tokens that FCM
 * reports as dead are deleted.
 */
class FcmChannel
{
    public function __construct(private FcmClient $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPush')) {
            return;
        }

        $push = $notification->toPush($notifiable);

        foreach ($notifiable->deviceTokens as $device) {
            if ($this->fcm->send($device->token, $push['title'], $push['body'], $push['data'] ?? [])) {
                $device->forceFill(['last_used_at' => now()])->save();
            } else {
                $device->delete();
            }
        }
    }
}
