<?php

declare(strict_types=1);

use App\Presentation\Http\Controllers\Fleet\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function (): void {
    Route::apiResource('vehicles', VehicleController::class);
});
