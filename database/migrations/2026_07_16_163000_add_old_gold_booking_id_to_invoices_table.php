<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'old_gold_booking_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('old_gold_booking_id')
                ->nullable()
                ->after('jewellery_order_id')
                ->constrained('old_gold_bookings')
                ->nullOnDelete();

            $table->unique('old_gold_booking_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'old_gold_booking_id')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['old_gold_booking_id']);
            $table->dropConstrainedForeignId('old_gold_booking_id');
        });
    }
};
