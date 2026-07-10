<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable()->after('driver_assigned_at');
            $table->string('delivery_failure_reason')->nullable()->after('delivered_at');
            $table->string('delivery_proof_image')->nullable()->after('delivery_failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->dropColumn([
                'picked_up_at',
                'delivery_failure_reason',
                'delivery_proof_image',
            ]);
        });
    }
};
