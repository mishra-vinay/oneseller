<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderPayments;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private RazorpayGateway $razorpay) {}

    public function show(Request $request, Order $order): RedirectResponse|View
    {
        $this->authorizeBuyer($request, $order);

        if ($order->status !== OrderStatus::PendingPayment || $order->payment_method !== PaymentMethod::Online) {
            return redirect()->route('order.show', $order);
        }

        $payment = $order->payments()->where('status', 'created')->latest('id')->first();

        if (! $payment) {
            try {
                $payment = $this->razorpay->createPayment($order);
            } catch (\Throwable $e) {
                Log::error('Razorpay order creation failed', ['order' => $order->number, 'error' => $e->getMessage()]);

                return view('checkout.pay', ['order' => $order, 'payment' => null, 'keyId' => null]);
            }
        }

        return view('checkout.pay', ['order' => $order, 'payment' => $payment, 'keyId' => $this->razorpay->keyId()]);
    }

    public function callback(Request $request, Order $order, OrderPayments $payments): RedirectResponse
    {
        $this->authorizeBuyer($request, $order);

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $payment = $order->payments()->where('gateway_order_id', $data['razorpay_order_id'])->first();

        if (! $payment || ! $this->razorpay->verifyPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return redirect()->route('checkout.pay', $order)->withErrors(['payment' => __('We could not verify that payment. If money left your account, it will be confirmed automatically or refunded.')]);
        }

        $payments->markPaid($payment, $data['razorpay_payment_id'], $data);

        return redirect()->route('order.show', $order)->with('placed', true);
    }

    private function authorizeBuyer(Request $request, Order $order): void
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);
    }
}
