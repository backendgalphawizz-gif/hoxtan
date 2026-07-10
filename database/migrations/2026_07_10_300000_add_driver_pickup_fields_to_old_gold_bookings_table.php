<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->timestamp('driver_accepted_at')->nullable()->after('driver_assigned_at');
            $table->timestamp('customer_verified_at')->nullable()->after('driver_accepted_at');
            $table->json('pickup_proof_images')->nullable()->after('customer_verified_at');
            $table->string('pickup_failure_reason')->nullable()->after('pickup_proof_images');
        });
    }

    public function down(): void
    {
        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'driver_accepted_at',
                'customer_verified_at',
                'pickup_proof_images',
                'pickup_failure_reason',
            ]);
        });
    }
};
