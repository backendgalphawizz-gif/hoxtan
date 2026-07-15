<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            if (! Schema::hasColumn('jewellery_products', 'has_size_variants')) {
                $table->boolean('has_size_variants')->default(false)->after('size');
            }
        });

        Schema::create('jewellery_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jewellery_product_id')->constrained('jewellery_products')->cascadeOnDelete();
            $table->string('size', 50);
            $table->decimal('weight_grams', 12, 3);
            $table->decimal('price', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['jewellery_product_id', 'size']);
            $table->index(['jewellery_product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jewellery_product_variants');

        Schema::table('jewellery_products', function (Blueprint $table) {
            if (Schema::hasColumn('jewellery_products', 'has_size_variants')) {
                $table->dropColumn('has_size_variants');
            }
        });
    }
};
