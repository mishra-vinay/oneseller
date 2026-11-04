<?php

namespace App\Notifications;

use App\Enums\ReturnStatus;
use App\Models\ReturnRequest;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the buyer each time their return moves on.
 */
class ReturnUpdated extends MarketplaceNotification
{
    public function __construct(public ReturnRequest $return) {}

    protected function title(object $notifiable): string
    {
        return "Return {$this->return->number}: {$this->return->status->label()}";
    }

    protected function link(object $notifiable): string
    {
        return route('account.returns.show', $this->return);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->return;
        $mail = (new MailMessage)->subject($this->title($notifiable));

        match ($r->status) {
            ReturnStatus::Approved => $mail->line('The seller approved your request. Please send the item back to them; keep the receipt.')
                ->line($r->seller_note ? "Seller's note: {$r->seller_note}" : ''),
            ReturnStatus::Rejected => $mail->line('The seller could not accept this return.')->line("Reason: {$r->seller_note}")
                ->line('If you disagree, reply to this email and our team will look into it.'),
            ReturnStatus::Received => $mail->line('The seller has received your item back.')
                ->line($r->type->value === 'refund' ? 'Your refund of '.Money::format($r->refund_amount).' is being processed.' : 'Your replacement will be shipped shortly.'),
            ReturnStatus::Refunded => $mail->line('We have refunded '.Money::format($r->refund_amount).' to you. It can take 5–7 working days to show in your account.'),
            ReturnStatus::Replaced => $mail->line('Your replacement is on its way.')->line("Courier: {$r->replacement_courier}")->line("Tracking number: {$r->replacement_tracking}"),
            default => $mail->line('Your return was updated.'),
        };

        return $mail->action('View your return', $this->link($notifiable));
    }
}
