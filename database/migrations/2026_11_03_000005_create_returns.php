<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('sub_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 15);
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('status', 20)->index();
            // What the buyer gets back: item value less its share of any coupon. Shipping is not refunded.
            $table->unsignedBigInteger('refund_amount');
            $table->string('seller_note')->nullable();
            $table->string('replacement_courier', 100)->nullable();
            $table->string('replacement_tracking', 100)->nullable();
            $table->boolean('restocked')->default(false);
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('return_requests');
    }
};
