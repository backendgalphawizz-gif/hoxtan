<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('primary_residence')->nullable()->after('email');
            $table->string('profile_photo')->nullable()->after('primary_residence');
            $table->boolean('market_alerts')->default(true)->after('profile_photo');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'gender',
                'date_of_birth',
                'primary_residence',
                'profile_photo',
                'market_alerts',
            ]);
        });
    }
};
