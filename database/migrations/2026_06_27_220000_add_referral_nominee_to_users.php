<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique()->after('phone');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')->constrained('users')->nullOnDelete();
            $table->string('nominee_name')->nullable()->after('block_reason');
            $table->string('nominee_relation')->nullable()->after('nominee_name');
            $table->string('nominee_phone', 20)->nullable()->after('nominee_relation');
            $table->date('nominee_date_of_birth')->nullable()->after('nominee_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropColumn([
                'referral_code',
                'referred_by_id',
                'nominee_name',
                'nominee_relation',
                'nominee_phone',
                'nominee_date_of_birth',
            ]);
        });
    }
};
