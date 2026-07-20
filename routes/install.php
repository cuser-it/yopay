<?php

declare(strict_types=1);

use App\Http\Controllers\Install\InstallController;
use App\Http\Middleware\EnsureInstallerAccess;
use App\Http\Middleware\EnsureInstallerAvailable;
use Illuminate\Support\Facades\Route;

Route::prefix('install')
    ->name('install.')
    ->middleware(EnsureInstallerAvailable::class)
    ->group(function (): void {
        Route::get('/', [InstallController::class, 'index'])->name('index');

        Route::middleware(EnsureInstallerAccess::class)->group(function (): void {
            Route::get('/requirements', [InstallController::class, 'requirements'])->name('requirements');
            Route::get('/database', [InstallController::class, 'database'])->name('database');
            Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');
            Route::get('/easypay', [InstallController::class, 'easypay'])->name('easypay');
            Route::post('/easypay', [InstallController::class, 'storeEasypay'])->name('easypay.store');
            Route::get('/administrator', [InstallController::class, 'administrator'])->name('administrator');
            Route::post('/complete', [InstallController::class, 'install'])->name('complete');
        });
    });
