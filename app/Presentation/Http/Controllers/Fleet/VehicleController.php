<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\RegisterVehicleAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Fleet\CreateVehicleRequest;
use App\Presentation\Http\Resources\Fleet\VehicleResource;
use Illuminate\Http\JsonResponse;

final class VehicleController extends Controller
{
    public function store(CreateVehicleRequest $request, RegisterVehicleAction $action): JsonResponse
    {
        $vehicle = $action->execute($request->toDTO());

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }
}
