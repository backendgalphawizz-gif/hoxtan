<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN source ENUM('admin', 'investment', 'redemption', 'refund', 'welcome_bonus', 'referral_bonus', 'other') DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN source ENUM('admin', 'investment', 'redemption', 'refund', 'other') DEFAULT 'other'");
    }
};
