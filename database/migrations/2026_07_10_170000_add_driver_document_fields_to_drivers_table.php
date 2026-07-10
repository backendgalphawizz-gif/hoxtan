<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('registration_card_image')->nullable()->after('vehicle_number');
            $table->string('licence_no', 30)->nullable()->after('registration_card_image');
            $table->string('licence_image')->nullable()->after('licence_no');
            $table->string('personal_no', 15)->nullable()->after('licence_image');
            $table->string('emergency_no', 15)->nullable()->after('personal_no');
            $table->string('aadhaar_front_image')->nullable()->after('emergency_no');
            $table->string('aadhaar_back_image')->nullable()->after('aadhaar_front_image');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'registration_card_image',
                'licence_no',
                'licence_image',
                'personal_no',
                'emergency_no',
                'aadhaar_front_image',
                'aadhaar_back_image',
            ]);
        });
    }
};
