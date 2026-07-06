<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Support\AdminPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupAdminCommand extends Command
{
    protected $signature = 'admin:setup
                            {--email= : Admin login email}
                            {--password= : Admin login password}
                            {--name=Super Admin : Admin display name}';

    protected $description = 'Create or reset the super admin account for Filament login';

    public function handle(): int
    {
        $email = strtolower(trim((string) (
            $this->option('email')
            ?: env('ADMIN_EMAIL', 'admin@gmail.com')
        )));

        $password = (string) (
            $this->option('password')
            ?: env('ADMIN_PASSWORD', '12345678')
        );

        if ($email === '' || $password === '') {
            $this->error('Email and password are required.');

            return self::FAILURE;
        }

        $role = AdminRole::query()->updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to every admin module.',
                'permissions' => AdminPermissions::allGranted(),
                'is_active' => true,
                'is_super_admin' => true,
            ],
        );

        $admin = Admin::query()->updateOrCreate(
            ['email' => $email],
            [
                'admin_role_id' => $role->id,
                'name' => (string) $this->option('name'),
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );

        $this->info('Super admin is ready.');
        $this->line('Email: '.$admin->email);
        $this->line('Password: '.$password);
        $this->line('Login URL: '.url('/admin/login'));

        return self::SUCCESS;
    }
}
