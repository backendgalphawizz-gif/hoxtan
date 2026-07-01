<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->string('metal_type');
            $table->decimal('quantity_grams', 14, 4);
            $table->decimal('rate_per_gram', 12, 2);
            $table->string('file_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
