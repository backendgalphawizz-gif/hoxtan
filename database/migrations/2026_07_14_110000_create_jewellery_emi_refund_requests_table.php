<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jewellery_emi_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();
            $table->foreignId('jewellery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('paid_amount', 14, 2);
            $table->decimal('cancellation_fee_percent', 5, 2)->default(10);
            $table->decimal('cancellation_fee_amount', 14, 2);
            $table->decimal('gst_percent', 5, 2)->default(3);
            $table->decimal('gst_amount', 14, 2);
            $table->decimal('deduction_amount', 14, 2);
            $table->decimal('refund_amount', 14, 2);
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'auto_approved',
                'rejected',
                'refunded',
            ])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('auto_approve_at')->nullable()->index();
            $table->boolean('auto_approved')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('refund_reference')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'auto_approve_at']);
            $table->index(['jewellery_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jewellery_emi_refund_requests');
    }
};
