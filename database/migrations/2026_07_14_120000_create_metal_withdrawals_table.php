<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('asset_source', ['gold', 'silver', 'sig']);
            $table->enum('metal_type', ['gold', 'silver']);
            $table->enum('input_mode', ['currency', 'weight']);
            $table->decimal('quantity_grams', 14, 4);
            $table->decimal('rate_per_gram', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending');
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->foreignId('sig_plan_id')->nullable()->constrained('sig_plans')->nullOnDelete();
            $table->foreignId('investment_id')->nullable()->constrained('investments')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payout_reference')->nullable();
            $table->boolean('auto_approved')->default(false);
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('auto_approve_at')->nullable()->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['asset_source', 'status']);
            $table->index(['status', 'auto_approve_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_withdrawals');
    }
};
