<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('pan_number', 10)->nullable();
            $table->string('aadhaar_number', 12)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('pan_document')->nullable();
            $table->string('aadhaar_front')->nullable();
            $table->string('aadhaar_back')->nullable();
            $table->string('selfie_photo')->nullable();
            $table->enum('face_verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('face_verification_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('metal_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('metal_type', ['gold', 'silver']);
            $table->decimal('rate_per_gram', 12, 2);
            $table->enum('source', ['live_sync', 'manual_override'])->default('live_sync');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('metal_type', ['gold', 'silver']);
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('quantity_grams', 14, 4);
            $table->decimal('rate_per_gram', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('investment_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('metal_type', ['gold', 'silver']);
            $table->decimal('target_grams', 14, 4);
            $table->decimal('current_grams', 14, 4)->default(0);
            $table->decimal('target_amount', 14, 2)->nullable();
            $table->date('target_date')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamps();
        });

        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('metal_type', ['gold', 'silver']);
            $table->decimal('quantity_grams', 14, 4);
            $table->decimal('amount', 14, 2);
            $table->enum('status', ['pending', 'approved', 'processing', 'dispatched', 'delivered', 'rejected', 'cancelled'])->default('pending');
            $table->text('delivery_address');
            $table->string('tracking_number')->nullable();
            $table->string('courier_name')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('description');
            $table->enum('source', ['admin', 'investment', 'redemption', 'refund', 'welcome_bonus', 'referral_bonus', 'other'])->default('other');
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gst_records', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->decimal('total_taxable_amount', 14, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('igst_amount', 12, 2)->default(0);
            $table->decimal('total_gst', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image');
            $table->string('link')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->enum('discount_type', ['percentage', 'flat'])->default('percentage');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->string('promo_code')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('static_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->enum('target', ['all', 'investors', 'specific'])->default('all');
            $table->json('target_user_ids')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sent', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->integer('new_users')->default(0);
            $table->integer('active_investors')->default(0);
            $table->decimal('gold_holdings_total', 14, 4)->default(0);
            $table->decimal('silver_holdings_total', 14, 4)->default(0);
            $table->decimal('revenue_total', 14, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('gst_collected', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
        Schema::dropIfExists('push_notifications');
        Schema::dropIfExists('static_pages');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('gst_records');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('redemptions');
        Schema::dropIfExists('investment_goals');
        Schema::dropIfExists('investments');
        Schema::dropIfExists('metal_rates');
        Schema::dropIfExists('kyc_details');
    }
};
