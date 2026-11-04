<?php

namespace App\Enums;

enum ShippingMode: string
{
    case Platform = 'platform';
    case Flat = 'flat';
    case Weight = 'weight';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Marketplace default',
            self::Flat => 'My own flat rate',
            self::Weight => 'Rate card by weight',
        };
    }
}
