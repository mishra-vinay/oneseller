<?php

namespace App\Enums;

enum LedgerType: string
{
    case Sale = 'sale';
    case Commission = 'commission';
    case CodCollected = 'cod_collected';
    case Refund = 'refund';
    case Payout = 'payout';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Sale credited',
            self::Commission => 'Commission',
            self::CodCollected => 'COD collected by seller',
            self::Refund => 'Refund',
            self::Payout => 'Payout',
            self::Adjustment => 'Adjustment',
        };
    }
}
