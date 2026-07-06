<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class EmergencyAdminSetupController extends Controller
{
    public function __invoke(string $token): JsonResponse
    {
        $expected = (string) config('admin.setup_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\AdminRoleSeeder',
            '--force' => true,
        ]);

        Artisan::call('admin:setup');

        return response()->json([
            'success' => true,
            'message' => 'Super admin account has been created/reset.',
            'email' => env('ADMIN_EMAIL', 'admin@gmail.com'),
            'password' => env('ADMIN_PASSWORD', '12345678'),
            'login_url' => url('/admin/login'),
            'important' => 'Remove ADMIN_SETUP_TOKEN from .env immediately after logging in.',
        ]);
    }
}
