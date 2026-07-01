<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        AdminRole::updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to every admin module.',
                'permissions' => AdminPermissions::allGranted(),
                'is_active' => true,
                'is_super_admin' => true,
            ],
        );

        AdminRole::updateOrCreate(
            ['slug' => 'support-staff'],
            [
                'name' => 'Support Staff',
                'description' => 'Sample limited role for user and KYC management.',
                'permissions' => $this->supportPermissions(),
                'is_active' => true,
                'is_super_admin' => false,
            ],
        );
    }

    private function supportPermissions(): array
    {
        $permissions = AdminPermissions::emptyMatrix();

        foreach (['dashboard', 'users', 'kyc', 'redemption_requests', 'delivery_tracking'] as $module) {
            $permissions[$module]['view'] = true;
            $permissions[$module]['edit'] = true;
        }

        $permissions['users']['create'] = true;
        $permissions['kyc']['create'] = true;

        return $permissions;
    }
}
