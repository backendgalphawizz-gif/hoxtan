<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->string('tracking_number')->nullable()->after('expected_delivery_date');
            $table->string('courier_name')->nullable()->after('tracking_number');
            $table->timestamp('dispatched_at')->nullable()->after('courier_name');
            $table->timestamp('delivered_at')->nullable()->after('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_number',
                'courier_name',
                'dispatched_at',
                'delivered_at',
            ]);
        });
    }
};
