<?php

use App\Http\Controllers\Admin\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes  (routes/web.php)
|--------------------------------------------------------------------------
|
| Public entry points. Admin panel lives at /admin (Filament).
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
