<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sig_installments MODIFY quantity_grams DECIMAL(16,6) NULL');
            DB::statement('ALTER TABLE sig_plans MODIFY metal_accumulated_grams DECIMAL(16,6) NOT NULL DEFAULT 0');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sig_installments ALTER COLUMN quantity_grams TYPE DECIMAL(16,6)');
            DB::statement('ALTER TABLE sig_plans ALTER COLUMN metal_accumulated_grams TYPE DECIMAL(16,6)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sig_installments MODIFY quantity_grams DECIMAL(14,4) NULL');
            DB::statement('ALTER TABLE sig_plans MODIFY metal_accumulated_grams DECIMAL(14,4) NOT NULL DEFAULT 0');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE sig_installments ALTER COLUMN quantity_grams TYPE DECIMAL(14,4)');
            DB::statement('ALTER TABLE sig_plans ALTER COLUMN metal_accumulated_grams TYPE DECIMAL(14,4)');
        }
    }
};
