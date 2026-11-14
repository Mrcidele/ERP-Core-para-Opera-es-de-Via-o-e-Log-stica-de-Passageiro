<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Crew\EmployeeController;
use App\Presentation\Http\Controllers\Fleet\VehicleController;
use App\Presentation\Http\Controllers\Maintenance\MaintenanceOrderController;
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
        Route::get('trips', [TripController::class, 'index'])->name('operations.trips.index');
        Route::post('trips', [TripController::class, 'store'])->name('operations.trips.store');
        Route::post('trips/{trip}/start', [TripController::class, 'start'])->name('operations.trips.start');
        Route::post('trips/{trip}/complete', [TripController::class, 'complete'])->name('operations.trips.complete');
        Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->name('operations.trips.cancel');
    });

    Route::prefix('maintenance')->group(function (): void {
        Route::get('orders', [MaintenanceOrderController::class, 'index'])->name('maintenance.orders.index');
        Route::post('orders', [MaintenanceOrderController::class, 'store'])->name('maintenance.orders.store');
        Route::patch('orders/{order}', [MaintenanceOrderController::class, 'update'])->name('maintenance.orders.update');
    });
});
