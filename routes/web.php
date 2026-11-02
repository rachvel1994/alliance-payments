<?php

declare(strict_types=1);

use App\Http\Controllers\ClientController;
use App\Http\Controllers\PaymentImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReallocationController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [WorkspaceController::class, 'overdue'])
    ->middleware(['auth', 'verified', 'can:clients.view'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/overdue', [WorkspaceController::class, 'overdue'])
        ->middleware('can:clients.view')->name('overdue.index');
    Route::get('/apartments', [WorkspaceController::class, 'apartments'])
        ->middleware('can:apartments.view')->name('apartments.index');
    Route::get('/contracts', [WorkspaceController::class, 'contracts'])
        ->middleware('can:contracts.view')->name('contracts.index');
    Route::get('/contracts/{contract}', [WorkspaceController::class, 'contract'])
        ->whereNumber('contract')->middleware('can:contracts.view')->name('contracts.show');
    Route::get('/payments', [WorkspaceController::class, 'payments'])
        ->middleware('can:payments.view')->name('payments.index');
    Route::get('/payments/{payment}', [WorkspaceController::class, 'payment'])
        ->whereNumber('payment')->middleware('can:payments.view')->name('payments.show');
    Route::get('/imports', [WorkspaceController::class, 'imports'])
        ->middleware('can:imports.view')->name('imports.index');
    Route::get('/imports/{import}', [WorkspaceController::class, 'import'])
        ->whereNumber('import')->middleware('can:imports.view')->name('imports.show');

    Route::post('/imports', [PaymentImportController::class, 'store'])
        ->middleware(['can:payments.import', 'throttle:10,1'])
        ->name('imports.store');

    Route::get('/clients', ClientController::class)
        ->middleware('can:clients.view')
        ->name('clients.index');

    Route::post('/clients/{client}/reallocate', ReallocationController::class)
        ->whereNumber('client')
        ->middleware('can:payments.reallocate')
        ->name('clients.reallocate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
