<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->string('discount_type', 20)->nullable()->after('making_charge_percent');
            $table->decimal('discount_value', 14, 2)->nullable()->after('discount_type');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
