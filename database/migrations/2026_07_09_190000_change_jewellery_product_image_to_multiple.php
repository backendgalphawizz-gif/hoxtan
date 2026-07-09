<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('jewellery_products')->whereNotNull('image')->get(['id', 'image']) as $row) {
            $value = $row->image;

            if ($value === null || $value === '') {
                continue;
            }

            $decoded = json_decode($value, true);

            if (! is_array($decoded)) {
                DB::table('jewellery_products')->where('id', $row->id)->update([
                    'image' => json_encode([$value]),
                ]);
            }
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE jewellery_products MODIFY image JSON NULL');
        } else {
            Schema::table('jewellery_products', function (Blueprint $table) {
                $table->text('image')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (DB::table('jewellery_products')->whereNotNull('image')->get(['id', 'image']) as $row) {
            $decoded = json_decode($row->image, true);

            if (is_array($decoded)) {
                DB::table('jewellery_products')->where('id', $row->id)->update([
                    'image' => $decoded[0] ?? null,
                ]);
            }
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE jewellery_products MODIFY image VARCHAR(255) NULL');
        } else {
            Schema::table('jewellery_products', function (Blueprint $table) {
                $table->string('image')->nullable()->change();
            });
        }
    }
};
