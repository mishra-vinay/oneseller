<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Order numbers like OS-2026-000123, sequential per year. Must be called inside a transaction.
 */
class OrderNumber
{
    public static function next(): string
    {
        $year = (int) now()->format('Y');

        $row = DB::table('order_sequences')->where('year', $year)->lockForUpdate()->first();

        if ($row) {
            $value = $row->last_value + 1;
            DB::table('order_sequences')->where('year', $year)->update(['last_value' => $value]);
        } else {
            $value = 1;
            DB::table('order_sequences')->insert(['year' => $year, 'last_value' => $value]);
        }

        return sprintf('OS-%d-%06d', $year, $value);
    }
}
