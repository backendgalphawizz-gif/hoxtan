<?php

use App\Http\Controllers\EmergencyAdminSetupController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes  (routes/web.php)
|--------------------------------------------------------------------------
|
| Public entry points. Admin panel lives at /admin (Filament).
|
*/

Route::get('/', LandingController::class)->name('home');

Route::get('/setup-admin/{token}', EmergencyAdminSetupController::class)
    ->name('admin.emergency-setup');
