<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->decimal('making_charge_percent', 5, 2)->nullable()->after('price');
        });

        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->dropColumn('compare_at_price');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->decimal('compare_at_price', 14, 2)->nullable()->after('price');
        });

        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->dropColumn('making_charge_percent');
        });
    }
};
