<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jewellery_gold_karat_rates', function (Blueprint $table) {
            $table->id();
            $table->string('purity', 10)->unique();
            $table->decimal('rate_per_gram', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jewellery_gold_karat_rates');
    }
};
