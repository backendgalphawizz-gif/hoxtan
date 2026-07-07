<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_employee')->default(false)->after('is_blocked');
            $table->string('employee_code', 32)->nullable()->after('is_employee');
        });

        Schema::create('user_restrictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('wallet_blocked')->default(false);
            $table->boolean('bonus_blocked')->default(false);
            $table->boolean('referral_blocked')->default(false);
            $table->boolean('withdrawal_hold')->default(false);
            $table->text('support_notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::table('kyc_details', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('pincode');
            $table->string('account_holder_name')->nullable()->after('bank_name');
            $table->string('account_number')->nullable()->after('account_holder_name');
            $table->string('ifsc_code', 20)->nullable()->after('account_number');
            $table->string('upi_id')->nullable()->after('ifsc_code');
            $table->string('bank_verification_status', 20)->default('pending')->after('upi_id');
        });

        Schema::create('jewellery_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2);
            $table->decimal('weight_grams', 10, 3)->nullable();
            $table->string('metal_type', 20)->nullable();
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'sold_out', 'coming_soon'])->default('in_stock');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('jewellery_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jewellery_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('INR');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('jewellery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total_amount', 14, 2);
            $table->enum('status', ['cart', 'pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('shipping_address')->nullable();
            $table->timestamps();
        });

        Schema::create('jewellery_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jewellery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jewellery_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('old_gold_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimated_weight_grams', 10, 3)->nullable();
            $table->decimal('quoted_amount', 14, 2)->nullable();
            $table->decimal('final_amount', 14, 2)->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('pickup_address')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->json('target_user_ids')->nullable()->after('promo_code');
            $table->boolean('for_all_users')->default(true)->after('target_user_ids');
        });

        Schema::table('investment_goals', function (Blueprint $table) {
            $table->boolean('admin_created')->default(false)->after('status');
            $table->json('target_user_ids')->nullable()->after('admin_created');
        });
    }

    public function down(): void
    {
        Schema::table('investment_goals', function (Blueprint $table) {
            $table->dropColumn(['admin_created', 'target_user_ids']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['target_user_ids', 'for_all_users']);
        });

        Schema::dropIfExists('old_gold_bookings');
        Schema::dropIfExists('jewellery_order_items');
        Schema::dropIfExists('jewellery_orders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('jewellery_cart_items');
        Schema::dropIfExists('jewellery_products');
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_holder_name', 'account_number', 'ifsc_code', 'upi_id', 'bank_verification_status']);
        });
        Schema::dropIfExists('user_restrictions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_employee', 'employee_code']);
        });
    }
};
