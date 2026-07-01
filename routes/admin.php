<?php

use App\Http\Controllers\Admin\InvoiceDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:admin'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/invoices/{invoice}/download', InvoiceDownloadController::class)
            ->name('admin.invoices.download');
    });
