<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('role', 20)->default('staff')->after('department_id');
            $table->index('role');
        });

        DB::table('employees')
            ->whereNotNull('created_by_employee_id')
            ->update(['role' => 'employee']);

        DB::table('employees')
            ->whereNull('created_by_employee_id')
            ->update(['role' => 'staff']);
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
