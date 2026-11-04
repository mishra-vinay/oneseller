<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\Cart;
use App\Notifications\AbandonedCartReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('oneseller:cart-reminders {--hours=24 : Hours of inactivity before reminding}')]
#[Description('Email buyers once about carts they left without checking out')]
class SendCartReminders extends Command
{
    public function handle(): int
    {
        $idleSince = now()->subHours((int) $this->option('hours'));
        $tooOld = now()->subDays(7);
        $sent = 0;

        Cart::query()
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($q) => $q->where('role', Role::Buyer)->where('status', AccountStatus::Active)->where('cart_reminders', true))
            ->whereHas('items')
            ->addSelect(['last_activity' => DB::table('cart_items')->selectRaw('max(updated_at)')->whereColumn('cart_items.cart_id', 'carts.id')])
            ->with('user')
            ->get()
            ->filter(fn (Cart $cart) => $cart->last_activity <= $idleSince && $cart->last_activity >= $tooOld)
            // Once per change: no reminder again until the buyer touches the cart.
            ->filter(fn (Cart $cart) => $cart->reminded_at === null || $cart->reminded_at < $cart->last_activity)
            ->each(function (Cart $cart) use (&$sent) {
                $cart->user->notify(new AbandonedCartReminder($cart));
                $cart->forceFill(['reminded_at' => now()])->saveQuietly();
                $sent++;
            });

        $this->info("Cart reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
