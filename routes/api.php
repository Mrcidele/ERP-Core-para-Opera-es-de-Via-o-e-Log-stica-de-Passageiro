<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Crew\EmployeeController;
use App\Presentation\Http\Controllers\Fleet\VehicleController;
use App\Presentation\Http\Controllers\Operations\LineController;
use App\Presentation\Http\Controllers\Operations\TripController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function (): void {
    Route::apiResource('vehicles', VehicleController::class);

    Route::prefix('crew')->group(function (): void {
        Route::get('employees', [EmployeeController::class, 'index'])->name('crew.employees.index');
        Route::post('employees', [EmployeeController::class, 'store'])->name('crew.employees.store');
    });

    Route::prefix('operations')->group(function (): void {
        Route::get('lines', [LineController::class, 'index'])->name('operations.lines.index');
        Route::post('trips', [TripController::class, 'store'])->name('operations.trips.store');
    });
});
