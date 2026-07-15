<?php

use App\Http\Controllers\Admin\InvoiceDownloadController;
use App\Http\Controllers\Admin\TemporaryExportDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:admin'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/invoices/{invoice}/download', InvoiceDownloadController::class)
            ->name('admin.invoices.download');

        Route::get('/exports/{token}/download', TemporaryExportDownloadController::class)
            ->middleware('signed')
            ->name('admin.exports.download');
    });
