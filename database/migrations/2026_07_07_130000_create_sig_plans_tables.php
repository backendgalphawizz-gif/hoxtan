<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sig_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('metal_type', ['gold', 'silver'])->default('gold');
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['active', 'paused', 'stopped'])->default('active');
            $table->string('linked_bank_name')->nullable();
            $table->string('linked_bank_last4', 4)->nullable();
            $table->unsignedSmallInteger('total_installments')->nullable();
            $table->unsignedSmallInteger('completed_installments')->default(0);
            $table->decimal('total_invested', 14, 2)->default(0);
            $table->decimal('metal_accumulated_grams', 14, 4)->default(0);
            $table->timestamp('next_debit_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('next_debit_at');
        });

        Schema::create('sig_installments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();
            $table->foreignId('sig_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('quantity_grams', 14, 4)->nullable();
            $table->decimal('rate_per_gram', 12, 2)->nullable();
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->timestamp('scheduled_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['sig_plan_id', 'status']);
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('sig_plan_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sig_plan_id');
        });

        Schema::dropIfExists('sig_installments');
        Schema::dropIfExists('sig_plans');
    }
};
