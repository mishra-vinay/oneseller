<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderPayments;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Confirms payments even if the buyer closes the browser before the callback.
 * Configure in Razorpay for the payment.captured and order.paid events.
 */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayGateway $razorpay, OrderPayments $payments): Response
    {
        if (! $razorpay->verifyWebhookSignature($request->getContent(), (string) $request->header('X-Razorpay-Signature'))) {
            return response('Invalid signature', 400);
        }

        $event = $request->json('event');
        $entity = $request->json('payload.payment.entity');

        if (in_array($event, ['payment.captured', 'order.paid'], true) && is_array($entity)) {
            $payment = Payment::where('gateway_order_id', $entity['order_id'] ?? null)->first();

            if ($payment && (int) ($entity['amount'] ?? 0) === $payment->amount) {
                $payments->markPaid($payment, (string) $entity['id'], ['webhook' => $event]);
            }
        }

        return response('OK');
    }
}
