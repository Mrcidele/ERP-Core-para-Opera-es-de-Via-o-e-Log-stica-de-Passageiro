<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Audit\AuditLogController;
use App\Presentation\Http\Controllers\Auth\AuthController;
use App\Presentation\Http\Controllers\Crew\EmployeeController;
use App\Presentation\Http\Controllers\Dashboard\DashboardController;
use App\Presentation\Http\Controllers\Fleet\VehicleController;
use App\Presentation\Http\Controllers\Fuel\FuelLogController;
use App\Presentation\Http\Controllers\HealthController;
use App\Presentation\Http\Controllers\Maintenance\MaintenanceOrderController;
use App\Presentation\Http\Controllers\Operations\LineController;
use App\Presentation\Http\Controllers\Operations\TripController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Público: usado pelo balanceador e pelo monitoramento para saber se a aplicação e o banco respondem.
    Route::get('health', HealthController::class)->name('health');

    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    // Tudo abaixo exige token; cada rota declara a permissão que a libera.
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        // Sem permissão própria: o controller entrega só os blocos que o usuário pode consultar.
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

        Route::prefix('fleet')->group(function (): void {
            Route::get('vehicles', [VehicleController::class, 'index'])->can('fleet.view')->name('fleet.vehicles.index');
            Route::get('vehicles/{vehicle}', [VehicleController::class, 'show'])->can('fleet.view')->name('fleet.vehicles.show');
            Route::post('vehicles', [VehicleController::class, 'store'])->can('fleet.manage')->name('fleet.vehicles.store');
            Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->can('fleet.manage')->name('fleet.vehicles.update');
            Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->can('fleet.manage')->name('fleet.vehicles.destroy');
        });

        Route::prefix('crew')->group(function (): void {
            Route::get('employees', [EmployeeController::class, 'index'])->can('crew.view')->name('crew.employees.index');
            Route::post('employees', [EmployeeController::class, 'store'])->can('crew.manage')->name('crew.employees.store');
        });

        Route::prefix('operations')->group(function (): void {
            Route::get('lines', [LineController::class, 'index'])->can('operations.view')->name('operations.lines.index');
            Route::post('lines', [LineController::class, 'store'])->can('operations.manage')->name('operations.lines.store');
            Route::put('lines/{line}', [LineController::class, 'update'])->can('operations.manage')->name('operations.lines.update');
            Route::delete('lines/{line}', [LineController::class, 'destroy'])->can('operations.manage')->name('operations.lines.destroy');
            Route::get('schedule-timeline', [TripController::class, 'timeline'])->can('operations.view')->name('operations.schedule-timeline');
            Route::get('trips/export', [TripController::class, 'export'])->can('operations.view')->name('operations.trips.export');
            Route::get('trips', [TripController::class, 'index'])->can('operations.view')->name('operations.trips.index');
            Route::post('trips', [TripController::class, 'store'])->can('operations.manage')->name('operations.trips.store');
            Route::post('trips/{trip}/start', [TripController::class, 'start'])->can('operations.manage')->name('operations.trips.start');
            Route::post('trips/{trip}/complete', [TripController::class, 'complete'])->can('operations.manage')->name('operations.trips.complete');
            Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->can('operations.manage')->name('operations.trips.cancel');
        });

        Route::prefix('fuel')->group(function (): void {
            Route::get('logs', [FuelLogController::class, 'index'])->can('fleet.view')->name('fuel.logs.index');
            Route::post('logs', [FuelLogController::class, 'store'])->can('fleet.manage')->name('fuel.logs.store');
        });

        Route::prefix('maintenance')->group(function (): void {
            Route::get('orders/export', [MaintenanceOrderController::class, 'export'])->can('maintenance.view')->name('maintenance.orders.export');
            Route::get('orders', [MaintenanceOrderController::class, 'index'])->can('maintenance.view')->name('maintenance.orders.index');
            Route::get('mechanics', [MaintenanceOrderController::class, 'mechanics'])->can('maintenance.view')->name('maintenance.mechanics.index');
            Route::post('orders', [MaintenanceOrderController::class, 'store'])->can('maintenance.manage')->name('maintenance.orders.store');
            Route::patch('orders/{order}', [MaintenanceOrderController::class, 'update'])->can('maintenance.manage')->name('maintenance.orders.update');
        });

        Route::get('audit-logs', [AuditLogController::class, 'index'])->can('audit.view')->name('audit-logs.index');
    });
});
