<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // platform: marketplace default; flat: the store's own flat rate; weight: rate card by parcel weight.
            $table->string('shipping_mode', 10)->default('platform')->after('commission_bps');
            $table->unsignedInteger('shipping_flat_paise')->nullable()->after('shipping_mode');
            // [{"up_to_grams": 500, "paise": 4000}, …] in ascending weight.
            $table->json('shipping_slabs')->nullable()->after('shipping_flat_paise');
            // Charged per extra kilogram (or part) above the heaviest slab.
            $table->unsignedInteger('shipping_extra_kg_paise')->nullable()->after('shipping_slabs');
            // Free shipping from this group subtotal; null in "platform" mode uses the marketplace threshold.
            $table->unsignedInteger('shipping_free_above_paise')->nullable()->after('shipping_extra_kg_paise');
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn([
            'shipping_mode', 'shipping_flat_paise', 'shipping_slabs', 'shipping_extra_kg_paise', 'shipping_free_above_paise',
        ]));
    }
};
