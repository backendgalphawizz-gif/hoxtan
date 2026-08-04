<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_tokens')) {
            return;
        }

        if (Schema::hasColumn('device_tokens', 'fcm_token')) {
            return;
        }

        if (! Schema::hasColumn('device_tokens', 'token')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE device_tokens CHANGE `token` `fcm_token` TEXT NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE device_tokens RENAME COLUMN token TO fcm_token');

            return;
        }

        // SQLite / others
        Schema::table('device_tokens', function ($table): void {
            $table->text('fcm_token')->nullable();
        });
        DB::table('device_tokens')->orderBy('id')->each(function ($row): void {
            DB::table('device_tokens')->where('id', $row->id)->update([
                'fcm_token' => $row->token,
            ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_tokens') || ! Schema::hasColumn('device_tokens', 'fcm_token')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE device_tokens CHANGE `fcm_token` `token` TEXT NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE device_tokens RENAME COLUMN fcm_token TO token');
        }
    }
};
