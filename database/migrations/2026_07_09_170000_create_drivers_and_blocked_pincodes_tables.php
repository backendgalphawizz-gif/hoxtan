<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 15);
            $table->string('email')->nullable();
            $table->string('vehicle_type', 30)->default('bike');
            $table->string('vehicle_number', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('phone');
        });

        Schema::create('blocked_pincodes', function (Blueprint $table) {
            $table->id();
            $table->string('pincode', 6);
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique('pincode');
            $table->index(['is_active', 'pincode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_pincodes');
        Schema::dropIfExists('drivers');
    }
};
