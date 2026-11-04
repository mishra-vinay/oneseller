<?php

namespace Tests\Feature\Orders;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

trait OrderTestHelpers
{
    protected function buyerWithAddress(): User
    {
        $buyer = User::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com']);
        $address = new Address(['name' => 'Asha Verma', 'phone' => '9876543210', 'address_line1' => '4 Lake View', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001', 'is_default' => true]);
        $buyer->addresses()->save($address);

        return $buyer;
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     */
    protected function checkout(User $buyer, array $lines, string $method = 'cod'): Order
    {
        $this->actingAs($buyer);

        foreach ($lines as [$product, $quantity]) {
            $this->post('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => $quantity]);
        }

        $this->post('/checkout/address', ['address_id' => $buyer->addresses()->first()->id])->assertRedirect('/checkout/review');
        $this->post('/checkout/place', ['payment_method' => $method])->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }
}
