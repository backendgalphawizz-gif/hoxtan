<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->foreignId('user_address_id')->nullable()->after('user_id')->constrained('user_addresses')->nullOnDelete();
            $table->decimal('metal_value', 14, 2)->default(0)->after('subtotal');
            $table->decimal('making_charge_amount', 14, 2)->default(0)->after('metal_value');
            $table->decimal('gst_percent', 5, 2)->default(0)->after('making_charge_amount');
            $table->decimal('gst_amount', 14, 2)->default(0)->after('gst_percent');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('gst_amount');
            $table->date('expected_delivery_date')->nullable()->after('shipping_address');
            $table->string('shipping_name')->nullable()->after('shipping_address');
            $table->string('shipping_phone', 20)->nullable()->after('shipping_name');
            $table->string('shipping_address_type', 20)->nullable()->after('shipping_phone');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_address_id');
            $table->dropColumn([
                'metal_value',
                'making_charge_amount',
                'gst_percent',
                'gst_amount',
                'discount_amount',
                'expected_delivery_date',
                'shipping_name',
                'shipping_phone',
                'shipping_address_type',
            ]);
        });
    }
};
