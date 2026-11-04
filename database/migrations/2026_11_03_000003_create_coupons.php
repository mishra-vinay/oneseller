<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->string('type', 10);
            // Basis points for percent coupons (1000 = 10%), paise for fixed ones.
            $table->unsignedInteger('value');
            $table->unsignedBigInteger('min_order_paise')->nullable();
            $table->unsignedBigInteger('max_discount_paise')->nullable();
            // Only this store's items count towards and receive the discount.
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable()->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('discount');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['coupon_id', 'user_id']);
        });

        // The discount is funded by the platform: sellers are still credited the full sale.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable()->after('shipping_total');
            $table->unsignedBigInteger('discount_total')->default(0)->after('coupon_code');
        });

        Schema::table('sub_orders', fn (Blueprint $table) => $table->unsignedBigInteger('discount')->default(0)->after('shipping'));
    }

    public function down(): void
    {
        Schema::table('sub_orders', fn (Blueprint $table) => $table->dropColumn('discount'));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['coupon_code', 'discount_total']));
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
