<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_withdrawals', function (Blueprint $table) {
            $table->foreignId('source_lot_id')
                ->nullable()
                ->after('investment_id')
                ->constrained('investments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metal_withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_lot_id');
        });
    }
};
