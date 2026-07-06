<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = AdminRole::where('slug', 'super-admin')->first();

        Admin::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@gmail.com')],
            [
                'admin_role_id' => $superAdminRole?->id,
                'name' => 'Super Admin',
                'password' => env('ADMIN_PASSWORD', '12345678'),
                'is_active' => true,
            ]
        );

        $supportRole = AdminRole::where('slug', 'support-staff')->first();

        Admin::updateOrCreate(
            ['email' => 'support@goldsilver.com'],
            [
                'admin_role_id' => $supportRole?->id,
                'name' => 'Support Staff',
                'password' => 'Support@123',
                'is_active' => true,
            ]
        );
    }
}
