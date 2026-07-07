<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jewellery_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('metal_type', ['gold', 'silver', 'both'])->default('both');
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('jewellery_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jewellery_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['jewellery_category_id', 'slug']);
        });

        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->foreignId('jewellery_category_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('jewellery_sub_category_id')->nullable()->after('jewellery_category_id')->constrained()->nullOnDelete();
            $table->string('image')->nullable()->after('description');
            $table->string('purity', 20)->nullable()->after('metal_type');
            $table->decimal('compare_at_price', 14, 2)->nullable()->after('price');
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jewellery_sub_category_id');
            $table->dropConstrainedForeignId('jewellery_category_id');
            $table->dropColumn(['image', 'purity', 'compare_at_price', 'sort_order']);
        });

        Schema::dropIfExists('jewellery_sub_categories');
        Schema::dropIfExists('jewellery_categories');
    }
};
