<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs already include these statuses in create migration.
        // This alters existing MySQL databases only.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sig_installments MODIFY status ENUM(
            'pending',
            'success',
            'failed',
            'withdrawal_pending',
            'withdrawal',
            'withdrawal_rejected'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sig_installments MODIFY status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending'");
    }
};
