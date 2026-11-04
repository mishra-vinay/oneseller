<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Services\Push\FcmClient;
use App\Support\Settings;
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
     * Email for everyone; in-panel for sellers and admins; push to any signed-in mobile device.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if ($notifiable instanceof User && $notifiable->role !== Role::Buyer) {
            $channels[] = 'database';
        }

        if ($notifiable instanceof User && app(FcmClient::class)->isConfigured() && $notifiable->deviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * Mobile push: the site name as the title, this notification's line as the body, and
     * the link and notification type as data for the app to route on.
     *
     * @return array{title: string, body: string, data: array<string, string>}
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => (string) app(Settings::class)->get('site_name'),
            'body' => $this->title($notifiable),
            'data' => ['type' => class_basename($this), 'url' => $this->link($notifiable)],
        ];
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
