<?php

namespace App\Console\Commands;

use App\Services\OrderPayments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oneseller:expire-unpaid-orders')]
#[Description('Cancel online orders whose payment never completed and return their stock')]
class ExpireUnpaidOrders extends Command
{
    public function handle(OrderPayments $payments): int
    {
        $this->info($payments->expireUnpaid().' unpaid orders expired.');

        return self::SUCCESS;
    }
}
