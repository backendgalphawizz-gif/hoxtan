<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['investment_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('investment_id')->nullable()->change();
            $table->string('metal_type')->nullable()->change();
            $table->decimal('quantity_grams', 14, 4)->nullable()->change();
            $table->decimal('rate_per_gram', 12, 2)->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('investment_id')
                ->references('id')
                ->on('investments')
                ->cascadeOnDelete();

            $table->foreignId('jewellery_order_id')
                ->nullable()
                ->after('investment_id')
                ->constrained('jewellery_orders')
                ->nullOnDelete();

            $table->unique('jewellery_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['jewellery_order_id']);
            $table->dropConstrainedForeignId('jewellery_order_id');
            $table->dropForeign(['investment_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('investment_id')->nullable(false)->change();
            $table->string('metal_type')->nullable(false)->change();
            $table->decimal('quantity_grams', 14, 4)->nullable(false)->change();
            $table->decimal('rate_per_gram', 12, 2)->nullable(false)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreign('investment_id')
                ->references('id')
                ->on('investments')
                ->cascadeOnDelete();
        });
    }
};
