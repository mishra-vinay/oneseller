<?php

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Notifications\LowStockDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('oneseller:low-stock-digest')]
#[Description('Email each seller a daily list of their low-stock products')]
class SendLowStockDigest extends Command
{
    public function handle(): int
    {
        $sent = 0;

        Store::live()->with('user')->each(function (Store $store) use (&$sent) {
            $items = ProductVariant::query()
                ->where('stock', '<=', ProductVariant::LOW_STOCK_THRESHOLD)
                ->whereHas('product', fn ($q) => $q->where('store_id', $store->id)->where('status', ProductStatus::Approved)->where('is_published', true))
                ->with('product:id,name')
                ->orderBy('stock')
                ->get()
                ->map(fn ($v) => ['name' => $v->product->name, 'sku' => $v->sku, 'stock' => $v->stock]);

            if ($items->isNotEmpty()) {
                $store->user->notify(new LowStockDigest($items));
                $sent++;
            }
        });

        $this->info("Low-stock digest sent to {$sent} sellers.");

        return self::SUCCESS;
    }
}
