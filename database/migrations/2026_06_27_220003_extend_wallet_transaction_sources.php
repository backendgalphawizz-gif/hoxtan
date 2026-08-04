<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite stores enums as strings and does not enforce the value list.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN source ENUM('admin', 'investment', 'redemption', 'refund', 'welcome_bonus', 'referral_bonus', 'other') DEFAULT 'other'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE wallet_transactions MODIFY COLUMN source ENUM('admin', 'investment', 'redemption', 'refund', 'other') DEFAULT 'other'");
    }
};
