<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Services\Payments\RazorpayGateway;
use App\Support\Settings;

class CheckoutOptions
{
    public function __construct(private RazorpayGateway $razorpay, private Settings $settings) {}

    /**
     * Online payment needs Razorpay keys; Cash on Delivery can be switched off in settings.
     *
     * @return array<int, PaymentMethod>
     */
    public function paymentMethods(): array
    {
        return array_values(array_filter([
            $this->razorpay->isConfigured() ? PaymentMethod::Online : null,
            $this->settings->get('cod_enabled') ? PaymentMethod::Cod : null,
        ]));
    }
}
