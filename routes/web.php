<?php

declare(strict_types=1);

use App\Http\Controllers\ApartmentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientManagementController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\PaymentImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReallocationController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [WorkspaceController::class, 'overdue'])
    ->middleware(['auth', 'verified', 'can:clients.view'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/projects/create', [ProjectController::class, 'create'])
        ->middleware('can:projects.create')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])
        ->middleware('can:projects.create')->name('projects.store');
    Route::get('/overdue', [WorkspaceController::class, 'overdue'])
        ->middleware('can:clients.view')->name('overdue.index');
    Route::get('/apartments', [WorkspaceController::class, 'apartments'])
        ->middleware('can:apartments.view')->name('apartments.index');
    Route::get('/apartments/create', [ApartmentController::class, 'create'])
        ->middleware('can:apartments.create')->name('apartments.create');
    Route::post('/apartments', [ApartmentController::class, 'store'])
        ->middleware('can:apartments.create')->name('apartments.store');
    Route::get('/apartments/{apartment}/edit', [ApartmentController::class, 'edit'])
        ->whereNumber('apartment')->middleware('can:apartments.update')->name('apartments.edit');
    Route::patch('/apartments/{apartment}', [ApartmentController::class, 'update'])
        ->whereNumber('apartment')->middleware('can:apartments.update')->name('apartments.update');
    Route::get('/contracts', [WorkspaceController::class, 'contracts'])
        ->middleware('can:contracts.view')->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])
        ->middleware('can:contracts.create')->name('contracts.create');
    Route::post('/contracts', [ContractController::class, 'store'])
        ->middleware('can:contracts.create')->name('contracts.store');
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

    Route::get('/clients/create', [ClientManagementController::class, 'create'])
        ->middleware('can:clients.create')->name('clients.create');
    Route::post('/clients', [ClientManagementController::class, 'store'])
        ->middleware('can:clients.create')->name('clients.store');
    Route::get('/clients/{client}/edit', [ClientManagementController::class, 'edit'])
        ->whereNumber('client')->middleware('can:clients.update')->name('clients.edit');
    Route::patch('/clients/{client}', [ClientManagementController::class, 'update'])
        ->whereNumber('client')->middleware('can:clients.update')->name('clients.update');

    Route::post('/clients/{client}/reallocate', ReallocationController::class)
        ->whereNumber('client')
        ->middleware('can:payments.reallocate')
        ->name('clients.reallocate');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
