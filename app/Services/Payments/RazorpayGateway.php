<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Razorpay Standard Checkout. The server creates a Razorpay order, the buyer pays in
 * Razorpay's popup, and the result is trusted only after its signature is verified
 * (on the browser callback, and again on the webhook).
 */
class RazorpayGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function isConfigured(): bool
    {
        return filled(config('services.razorpay.key_id')) && filled(config('services.razorpay.key_secret'));
    }

    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    public function createPayment(Order $order): Payment
    {
        $response = Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))
            ->acceptJson()
            ->timeout(15)
            ->post(self::API.'/orders', [
                'amount' => $order->grand_total,
                'currency' => 'INR',
                'receipt' => $order->number,
                'notes' => ['order_number' => $order->number],
            ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw new RuntimeException('Razorpay order creation failed: '.$response->body());
        }

        return $order->payments()->create([
            'gateway' => 'razorpay',
            'gateway_order_id' => $response->json('id'),
            'amount' => $order->grand_total,
            'status' => 'created',
        ]);
    }

    public function verifyPaymentSignature(string $gatewayOrderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $gatewayOrderId.'|'.$paymentId, (string) config('services.razorpay.key_secret'));

        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
