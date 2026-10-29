<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every marketplace event email. Sellers and admins also get an in-panel
 * notification so nothing depends on email being read; buyers get email only.
 */
abstract class MarketplaceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Short line shown in the panel notification list.
     */
    abstract protected function title(object $notifiable): string;

    /**
     * Where the panel notification links to.
     */
    abstract protected function link(object $notifiable): string;

    abstract public function toMail(object $notifiable): MailMessage;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && $notifiable->role !== Role::Buyer) {
            return ['mail', 'database'];
        }

        return ['mail'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title($notifiable),
            'url' => $this->link($notifiable),
        ];
    }
}
