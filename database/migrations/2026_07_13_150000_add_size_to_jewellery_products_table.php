<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->string('size', 50)->nullable()->after('purity');
        });
    }

    public function down(): void
    {
        Schema::table('jewellery_products', function (Blueprint $table) {
            $table->dropColumn('size');
        });
    }
};
