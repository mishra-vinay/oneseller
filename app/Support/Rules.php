<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Validation rules shared across forms.
 */
class Rules
{
    public const MOBILE = 'regex:/^[6-9]\d{9}$/';

    public const PINCODE = 'regex:/^[1-9]\d{5}$/';

    public const GSTIN = 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    /** Rupee amount with up to two decimals, e.g. 499 or 499.50. */
    public const RUPEES = 'regex:/^\d{1,8}(\.\d{1,2})?$/';

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            '*.regex' => 'Please check the format of this field.',
            'phone.regex' => 'Enter a 10-digit Indian mobile number.',
            'store_phone.regex' => 'Enter a 10-digit Indian mobile number.',
            'pincode.regex' => 'Enter a 6-digit PIN code.',
            'gstin.regex' => 'Enter a valid 15-character GSTIN, e.g. 22AAAAA0000A1Z5.',
            'price.regex' => 'Enter an amount in rupees, e.g. 499 or 499.50.',
            'compare_at_price.regex' => 'Enter an amount in rupees, e.g. 499 or 499.50.',
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function address(string $prefix = ''): array
    {
        return [
            $prefix.'address_line1' => ['required', 'string', 'max:255'],
            $prefix.'address_line2' => ['nullable', 'string', 'max:255'],
            $prefix.'city' => ['required', 'string', 'max:100'],
            $prefix.'state' => ['required', Rule::in(IndianStates::ALL)],
            $prefix.'pincode' => ['required', self::PINCODE],
        ];
    }
}
