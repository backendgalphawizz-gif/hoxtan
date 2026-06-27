<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->enum('role', ['user', 'investor'])->default('user')->after('password');
            $table->boolean('is_blocked')->default(false)->after('role');
            $table->boolean('is_verified')->default(false)->after('is_blocked');
            $table->enum('kyc_status', ['pending', 'submitted', 'under_review', 'approved', 'rejected'])->default('pending')->after('is_verified');
            $table->decimal('gold_holdings', 14, 4)->default(0)->after('kyc_status');
            $table->decimal('silver_holdings', 14, 4)->default(0)->after('gold_holdings');
            $table->decimal('wallet_balance', 14, 2)->default(0)->after('silver_holdings');
            $table->timestamp('blocked_at')->nullable()->after('wallet_balance');
            $table->text('block_reason')->nullable()->after('blocked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'role', 'is_blocked', 'is_verified', 'kyc_status',
                'gold_holdings', 'silver_holdings', 'wallet_balance',
                'blocked_at', 'block_reason',
            ]);
        });
    }
};
