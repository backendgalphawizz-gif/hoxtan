<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->string('digilocker_client_id')->nullable()->after('aadhaar_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->dropColumn('digilocker_client_id');
        });
    }
};
