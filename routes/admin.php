<?php

use App\Http\Controllers\Admin\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes  (routes/admin.php)
|--------------------------------------------------------------------------
|
| MVC Structure:
|   Models      → app/Models/
|   Controllers → app/Http/Controllers/Admin/  +  app/Filament/Resources/
|   Views       → resources/views/admin/{section}/*.blade.php
|   Routes      → routes/web.php  +  routes/admin.php
|
| Filament CRUD panels are registered at /admin/* via Filament Resources.
| Add custom admin controller routes below when needed.
|
*/

Route::middleware(['web', 'auth:admin', 'admin.active'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Custom admin actions (Filament handles /admin dashboard & CRUD)
        // Route::get('/export/users', [UserExportController::class, 'index'])->name('export.users');
    });
