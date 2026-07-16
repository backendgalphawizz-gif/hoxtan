<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table): void {
                $table->id();
                $table->morphs('tokenable');
                $table->text('fcm_token');
                $table->string('token_hash', 64)->unique();
                $table->string('platform', 20)->nullable();
                $table->string('device_name')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasColumn('device_tokens', 'token_hash')) {
            Schema::table('device_tokens', function (Blueprint $table): void {
                $table->string('token_hash', 64)->nullable()->after('token');
            });
        }

        $tokenColumn = Schema::hasColumn('device_tokens', 'fcm_token')
            ? 'fcm_token'
            : (Schema::hasColumn('device_tokens', 'token') ? 'token' : null);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' && $tokenColumn !== null) {
            foreach (['device_tokens_token_unique', 'device_tokens_fcm_token_unique', 'token', 'fcm_token'] as $index) {
                try {
                    DB::statement("ALTER TABLE device_tokens DROP INDEX `{$index}`");
                } catch (\Throwable) {
                }
            }
            DB::statement("ALTER TABLE device_tokens MODIFY `{$tokenColumn}` TEXT NOT NULL");
        }

        if ($tokenColumn !== null) {
            $rows = DB::table('device_tokens')
                ->whereNull('token_hash')
                ->orWhere('token_hash', '')
                ->get(['id', $tokenColumn]);

            foreach ($rows as $row) {
                $value = $row->{$tokenColumn} ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                DB::table('device_tokens')->where('id', $row->id)->update([
                    'token_hash' => hash('sha256', (string) $value),
                ]);
            }
        }

        // Unique index on token_hash
        try {
            Schema::table('device_tokens', function (Blueprint $table): void {
                $table->unique('token_hash');
            });
        } catch (\Throwable) {
            // already unique
        }
    }

    public function down(): void
    {
        //
    }
};
