<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 15)->nullable()->unique();
            $table->string('employee_code', 32)->nullable()->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('created_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['department_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
