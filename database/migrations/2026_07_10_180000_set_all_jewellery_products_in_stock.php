<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('jewellery_products')->update(['stock_status' => 'in_stock']);
    }

    public function down(): void
    {
        // Stock status history is not restored.
    }
};
