<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->string('payment_mode', 20)->default('full')->after('total_amount');
            $table->foreignId('jewellery_emi_plan_id')
                ->nullable()
                ->after('payment_mode')
                ->constrained('jewellery_emi_plans')
                ->nullOnDelete();
            $table->unsignedSmallInteger('emi_tenure')->nullable()->after('jewellery_emi_plan_id');
            $table->decimal('total_emi_cost', 14, 2)->nullable()->after('emi_tenure');
            $table->decimal('monthly_emi_amount', 14, 2)->nullable()->after('total_emi_cost');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jewellery_emi_plan_id');
            $table->dropColumn([
                'payment_mode',
                'emi_tenure',
                'total_emi_cost',
                'monthly_emi_amount',
            ]);
        });
    }
};
