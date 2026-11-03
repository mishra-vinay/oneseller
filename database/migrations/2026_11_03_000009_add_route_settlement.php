<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The seller's Razorpay Route linked account (acc_…), set by an admin.
        Schema::table('stores', fn (Blueprint $table) => $table->string('razorpay_account_id', 40)->nullable()->after('gstin'));

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->string('razorpay_transfer_id', 40)->nullable()->after('tracking_number');
            // on_hold → released (on delivery) | reversed (on cancellation) | failed
            $table->string('transfer_status', 15)->nullable()->after('razorpay_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', fn (Blueprint $table) => $table->dropColumn(['razorpay_transfer_id', 'transfer_status']));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('razorpay_account_id'));
    }
};
