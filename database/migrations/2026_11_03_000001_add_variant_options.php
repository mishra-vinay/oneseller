<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // e.g. ["Size", "Colour"]; null for a product with a single variant.
        Schema::table('products', fn (Blueprint $table) => $table->json('option_names')->nullable()->after('description'));

        // e.g. {"Size": "M", "Colour": "Red"}; null for the single variant of a product without options.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('options')->nullable()->after('sku');
            $table->unsignedSmallInteger('position')->default(0)->after('options');
        });

        Schema::table('order_items', fn (Blueprint $table) => $table->string('variant_label')->nullable()->after('product_name'));
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('variant_label'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn(['options', 'position']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('option_names'));
    }
};
