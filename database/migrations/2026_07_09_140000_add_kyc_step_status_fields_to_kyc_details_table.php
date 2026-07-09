<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->string('pan_verification_status', 30)->default('action_required')->after('pan_number');
            $table->string('aadhaar_verification_status', 30)->default('action_required')->after('aadhaar_number');
            $table->timestamp('pan_verified_at')->nullable()->after('pan_verification_status');
            $table->timestamp('aadhaar_verified_at')->nullable()->after('aadhaar_verification_status');
            $table->timestamp('bank_submitted_at')->nullable()->after('bank_verification_status');
            $table->timestamp('face_submitted_at')->nullable()->after('face_verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->dropColumn([
                'pan_verification_status',
                'aadhaar_verification_status',
                'pan_verified_at',
                'aadhaar_verified_at',
                'bank_submitted_at',
                'face_submitted_at',
            ]);
        });
    }
};
