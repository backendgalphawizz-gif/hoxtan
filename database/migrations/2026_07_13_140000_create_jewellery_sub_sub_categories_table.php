<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jewellery_sub_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jewellery_sub_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['jewellery_sub_category_id', 'slug'], 'jewellery_sub_sub_cat_slug_unique');
        });

        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->foreignId('jewellery_sub_sub_category_id')
                ->nullable()
                ->after('jewellery_sub_category_id')
                ->constrained('jewellery_sub_sub_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jewellery_sub_sub_category_id');
        });

        Schema::dropIfExists('jewellery_sub_sub_categories');
    }
};
