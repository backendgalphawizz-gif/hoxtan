<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->string('delivery_otp', 10)->nullable()->after('expected_delivery_date');
        });

        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->string('delivery_otp', 10)->nullable()->after('pickup_phone');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->dropColumn('delivery_otp');
        });

        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->dropColumn('delivery_otp');
        });
    }
};
