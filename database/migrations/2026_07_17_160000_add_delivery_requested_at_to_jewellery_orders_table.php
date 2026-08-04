<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->timestamp('delivery_requested_at')->nullable()->after('delivery_otp');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->dropColumn('delivery_requested_at');
        });
    }
};
