<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->foreignId('driver_id')
                ->nullable()
                ->after('user_address_id')
                ->constrained('drivers')
                ->nullOnDelete();
            $table->timestamp('driver_assigned_at')->nullable()->after('driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('old_gold_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn('driver_assigned_at');
        });
    }
};
