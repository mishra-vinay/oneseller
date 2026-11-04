<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Notifications\Messages\MailMessage;

class StoreReviewed extends MarketplaceNotification
{
    public function __construct(public Store $store) {}

    protected function title(object $notifiable): string
    {
        return $this->store->isApproved() ? 'Your store has been approved' : 'Your store registration was not approved';
    }

    protected function link(object $notifiable): string
    {
        return route('seller.dashboard');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title($notifiable))->greeting("Hello {$notifiable->name},");

        return $this->store->isApproved()
            ? $mail->line("{$this->store->name} is approved. You can now add products to the marketplace.")->action('Open seller dashboard', route('seller.dashboard'))
            : $mail->line("{$this->store->name} was not approved.")->line('Reason: '.$this->store->rejection_reason);
    }
}
