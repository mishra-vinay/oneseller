<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('pincode', 6);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            // Identifies a guest's cart via a cookie until they log in.
            $table->string('token', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('order_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_value');
        });

        // Money columns are paise. Shipping address is a snapshot, not a reference.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('ship_name');
            $table->string('ship_phone', 20);
            $table->string('ship_address_line1');
            $table->string('ship_address_line2')->nullable();
            $table->string('ship_city', 100);
            $table->string('ship_state', 100);
            $table->string('ship_pincode', 6);
            $table->unsignedBigInteger('items_total');
            $table->unsignedBigInteger('shipping_total');
            $table->unsignedBigInteger('grand_total');
            $table->unsignedBigInteger('refunded_total')->default(0);
            $table->string('payment_method', 10);
            $table->string('payment_status', 20)->index();
            $table->string('status', 20)->index();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // One per seller per order. Commission rate is a snapshot of the rate on the day of the order.
        Schema::create('sub_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('number', 24)->unique();
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('items_subtotal');
            $table->unsignedBigInteger('shipping');
            $table->unsignedSmallInteger('commission_bps');
            $table->unsignedBigInteger('commission_amount');
            $table->unsignedBigInteger('net_payout');
            $table->string('courier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('sku', 64);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('gateway_payment_id')->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20);
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        // Append-only seller statement. Amounts are signed paise; balance_after is the running balance per store.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('sub_orders');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('order_sequences');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('addresses');
    }
};
