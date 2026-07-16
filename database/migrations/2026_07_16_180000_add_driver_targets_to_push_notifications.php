<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_notifications')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE push_notifications MODIFY COLUMN target ENUM('all', 'investors', 'specific', 'all_drivers', 'specific_drivers') NOT NULL DEFAULT 'all'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('push_notifications')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE push_notifications MODIFY COLUMN target ENUM('all', 'investors', 'specific') NOT NULL DEFAULT 'all'");
        }
    }
};
