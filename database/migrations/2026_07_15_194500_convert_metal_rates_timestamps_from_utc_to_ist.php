<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * metal_rates timestamps were stored while APP_TIMEZONE was UTC.
     * Convert wall-clock values to Asia/Kolkata so displays match India local time.
     */
    public function up(): void
    {
        foreach (DB::table('metal_rates')->orderBy('id')->get(['id', 'created_at', 'updated_at']) as $row) {
            DB::table('metal_rates')->where('id', $row->id)->update([
                'created_at' => $this->utcWallClockToIst($row->created_at),
                'updated_at' => $this->utcWallClockToIst($row->updated_at),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('metal_rates')->orderBy('id')->get(['id', 'created_at', 'updated_at']) as $row) {
            DB::table('metal_rates')->where('id', $row->id)->update([
                'created_at' => $this->istWallClockToUtc($row->created_at),
                'updated_at' => $this->istWallClockToUtc($row->updated_at),
            ]);
        }
    }

    private function utcWallClockToIst(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')
            ->timezone('Asia/Kolkata')
            ->format('Y-m-d H:i:s');
    }

    private function istWallClockToUtc(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value, 'Asia/Kolkata')
            ->timezone('UTC')
            ->format('Y-m-d H:i:s');
    }
};
