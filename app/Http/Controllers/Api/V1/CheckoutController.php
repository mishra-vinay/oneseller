<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\CartChangedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutOptions;
use App\Services\OrderPayments;
use App\Services\OrderPlacement;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Online payment from the app:
 *  1. POST /checkout with payment_method=online → order plus a razorpay block;
 *  2. open Razorpay Checkout in the app with razorpay.key_id and razorpay.order_id;
 *  3. POST /orders/{number}/payment with the three values Razorpay returns.
 * The Razorpay webhook also confirms the payment if step 3 never arrives.
 */
class CheckoutController extends Controller
{
    public function place(Request $request, CartService $carts, CheckoutOptions $options, OrderPlacement $placement, RazorpayGateway $razorpay): JsonResponse
    {
        $data = $request->validate([
            'address_id' => ['required', Rule::exists('addresses', 'id')->where('user_id', $request->user()->id)],
            'payment_method' => ['required', Rule::in(array_map(fn ($m) => $m->value, $options->paymentMethods()))],
            'coupon' => ['nullable', 'string', 'max:40'],
        ]);

        $cart = $carts->current();
        $summary = $carts->summary($cart);

        if (! $cart || $summary['count'] === 0) {
            throw ValidationException::withMessages(['cart' => __('Your cart is empty.')]);
        }

        if ($summary['has_problems']) {
            throw ValidationException::withMessages(['cart' => __('Some items in your cart are no longer available in the quantity you chose. Please review your cart.')]);
        }

        try {
            $order = $placement->place($request->user(), $cart, $request->user()->addresses()->findOrFail($data['address_id']), PaymentMethod::from($data['payment_method']), $data['coupon'] ?? null);
        } catch (CartChangedException $e) {
            throw ValidationException::withMessages(['cart' => $e->getMessage()]);
        }

        return response()->json([
            'order' => new OrderResource($order->load('subOrders.store', 'subOrders.items')),
            'razorpay' => $order->payment_method === PaymentMethod::Online ? $this->razorpayBlock($order, $razorpay) : null,
        ], 201);
    }

    /**
     * Retry payment for an online order still awaiting it.
     */
    public function payment(Request $request, Order $order, RazorpayGateway $razorpay): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);

        if ($order->status !== OrderStatus::PendingPayment) {
            throw ValidationException::withMessages(['order' => __('This order is not waiting for payment.')]);
        }

        return response()->json(['razorpay' => $this->razorpayBlock($order, $razorpay)]);
    }

    public function verify(Request $request, Order $order, RazorpayGateway $razorpay, OrderPayments $payments): OrderResource
    {
        abort_unless($order->buyer_id === $request->user()->id, 404);

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $payment = $order->payments()->where('gateway_order_id', $data['razorpay_order_id'])->first();

        if (! $payment || ! $razorpay->verifyPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            throw ValidationException::withMessages(['razorpay_signature' => __('We could not verify that payment.')]);
        }

        $payments->markPaid($payment, $data['razorpay_payment_id'], $data);

        return new OrderResource($order->fresh()->load('subOrders.store', 'subOrders.items'));
    }

    /**
     * @return array{key_id: string, order_id: ?string, amount: int, currency: string}
     */
    private function razorpayBlock(Order $order, RazorpayGateway $razorpay): array
    {
        $payment = $order->payments()->where('status', 'created')->latest('id')->first() ?? $razorpay->createPayment($order);

        return [
            'key_id' => $razorpay->keyId(),
            'order_id' => $payment->gateway_order_id,
            'amount' => $order->grand_total,
            'currency' => 'INR',
        ];
    }
}
