<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holding_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('certificate_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_holder_name');
            $table->string('metal_type');
            $table->decimal('holding_grams', 14, 4);
            $table->string('purity');
            $table->string('file_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holding_certificates');
    }
};
