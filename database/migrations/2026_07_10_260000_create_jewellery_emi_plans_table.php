<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jewellery_emi_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tenure_months');
            $table->decimal('interest_rate_percent', 5, 2)->default(0);
            $table->decimal('min_order_amount', 14, 2)->nullable();
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('tenure_months');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jewellery_emi_plans');
    }
};
