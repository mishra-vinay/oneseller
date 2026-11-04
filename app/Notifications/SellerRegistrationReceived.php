<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Notifications\Messages\MailMessage;

class SellerRegistrationReceived extends MarketplaceNotification
{
    public function __construct(public Store $store) {}

    protected function title(object $notifiable): string
    {
        return $this->store->isApproved()
            ? 'Your store is live'
            : 'Registration received, awaiting approval';
    }

    protected function link(object $notifiable): string
    {
        return route('seller.dashboard');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject("We received your store registration: {$this->store->name}")
            ->greeting("Hello {$notifiable->name},");

        return $this->store->isApproved()
            ? $mail->line('Your store is approved and ready. You can start adding products now.')->action('Open seller dashboard', route('seller.dashboard'))
            : $mail->line('Thanks for registering. Our team is reviewing your store and will email you once it is approved.');
    }
}
