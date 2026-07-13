<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jewellery_order_emi_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jewellery_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->decimal('amount', 14, 2);
            $table->date('due_date');
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('marked_paid_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['jewellery_order_id', 'installment_number'], 'jewellery_emi_installment_unique');
            $table->index(['jewellery_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jewellery_order_emi_installments');
    }
};
