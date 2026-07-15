<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('metal_withdrawals', 'from_holdings')) {
                $table->boolean('from_holdings')->default(false)->after('source_lot_id');
                $table->index('from_holdings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('metal_withdrawals', function (Blueprint $table) {
            if (Schema::hasColumn('metal_withdrawals', 'from_holdings')) {
                $table->dropIndex(['from_holdings']);
                $table->dropColumn('from_holdings');
            }
        });
    }
};
