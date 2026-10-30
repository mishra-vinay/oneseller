<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use OrderTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.razorpay' => ['key_id' => 'rzp_test_key', 'key_secret' => 'test_secret', 'webhook_secret' => 'hook_secret']]);
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_RZP123', 'amount' => 1, 'currency' => 'INR'])]);
    }

    private function placeOnlineOrder(): Order
    {
        $order = $this->checkout($this->buyerWithAddress(), [[Product::factory()->priced(150000)->stocked(5)->create(), 2]], 'online');

        $this->get("/checkout/pay/{$order->number}")->assertOk()->assertSee('order_RZP123')->assertSee('rzp_test_key');

        return $order;
    }

    public function test_online_order_waits_for_payment_then_is_confirmed_by_signed_callback(): void
    {
        Notification::fake();
        $order = $this->placeOnlineOrder();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertSame(SubOrderStatus::PendingPayment, $order->subOrders->first()->status);
        Notification::assertNothingSent();
        Http::assertSent(fn ($request) => $request['amount'] === 300000 && $request['currency'] === 'INR' && $request['receipt'] === $order->number);

        $this->post("/checkout/pay/{$order->number}", [
            'razorpay_order_id' => 'order_RZP123',
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_signature' => 'forged',
        ])->assertRedirect("/checkout/pay/{$order->number}");
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

        $this->post("/checkout/pay/{$order->number}", [
            'razorpay_order_id' => 'order_RZP123',
            'razorpay_payment_id' => 'pay_ABC',
            'razorpay_signature' => hash_hmac('sha256', 'order_RZP123|pay_ABC', 'test_secret'),
        ])->assertRedirect("/order/{$order->number}");

        $order->refresh();
        $this->assertSame(OrderStatus::Placed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(SubOrderStatus::Placed, $order->subOrders->first()->status);
        $this->assertSame('pay_ABC', $order->payments->first()->gateway_payment_id);
        Notification::assertSentToTimes($order->buyer, OrderConfirmation::class, 1);
    }

    public function test_webhook_confirms_payment_once_and_rejects_bad_signatures(): void
    {
        Notification::fake();
        $order = $this->placeOnlineOrder();

        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_XYZ', 'order_id' => 'order_RZP123', 'amount' => 300000]]]]);

        $this->call('POST', '/webhooks/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'bad'], $body)->assertStatus(400);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);

        $signature = hash_hmac('sha256', $body, 'hook_secret');
        $this->call('POST', '/webhooks/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature], $body)->assertOk();
        $this->call('POST', '/webhooks/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature], $body)->assertOk();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        Notification::assertSentToTimes($order->buyer, OrderConfirmation::class, 1);
    }

    public function test_unpaid_orders_expire_and_return_stock(): void
    {
        $order = $this->placeOnlineOrder();
        $variant = $order->subOrders->first()->items->first()->variant;
        $this->assertSame(3, $variant->fresh()->stock);

        $this->artisan('oneseller:expire-unpaid-orders')->assertSuccessful();
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status, 'still inside the payment window');

        $this->travel(31)->minutes();
        $this->artisan('oneseller:expire-unpaid-orders')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, $order->fresh()->payment_status);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_online_payment_is_hidden_when_razorpay_is_not_configured(): void
    {
        config(['services.razorpay.key_id' => null]);
        $buyer = $this->buyerWithAddress();

        $this->actingAs($buyer)->post('/cart/items', ['variant_id' => Product::factory()->create()->defaultVariant->id, 'quantity' => 1]);
        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id]);

        $this->get('/checkout/review')->assertDontSee('Online payment');
        $this->post('/checkout/place', ['payment_method' => 'online'])->assertSessionHasErrors('payment_method');
    }
}
