<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_order_items', function (Blueprint $table) {
            $table->foreignId('jewellery_product_variant_id')
                ->nullable()
                ->after('jewellery_product_id')
                ->constrained('jewellery_product_variants')
                ->nullOnDelete();
            $table->string('size')->nullable()->after('jewellery_product_variant_id');
            $table->decimal('weight_grams', 10, 3)->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jewellery_product_variant_id');
            $table->dropColumn(['size', 'weight_grams']);
        });
    }
};
