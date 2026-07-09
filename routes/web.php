<?php

use App\Http\Controllers\DeployArtisanController;
use App\Http\Controllers\EmergencyAdminSetupController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\WebsitePageController;
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

foreach (config('app_content.website_pages', []) as $websitePage) {
    Route::get('/'.$websitePage['slug'], [WebsitePageController::class, 'show'])
        ->defaults('slug', $websitePage['slug'])
        ->name('website.'.$websitePage['key']);
}

Route::get('/setup-admin/{token}', EmergencyAdminSetupController::class)
    ->name('admin.emergency-setup');

Route::get('/deploy/{command}', [DeployArtisanController::class, 'run'])
    ->where('command', 'migrate|optimize-clear|storage-link')
    ->name('deploy.artisan');
