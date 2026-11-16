<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Fleet;

use App\Domain\Fleet\Actions\DeleteVehicleAction;
use App\Domain\Fleet\Actions\GetVehicleAction;
use App\Domain\Fleet\Actions\ListVehiclesAction;
use App\Domain\Fleet\Actions\RegisterVehicleAction;
use App\Domain\Fleet\Actions\UpdateVehicleAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Fleet\CreateVehicleRequest;
use App\Presentation\Http\Requests\Fleet\ListVehiclesRequest;
use App\Presentation\Http\Requests\Fleet\UpdateVehicleRequest;
use App\Presentation\Http\Resources\Fleet\VehicleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class VehicleController extends Controller
{
    public function index(ListVehiclesRequest $request, ListVehiclesAction $action): AnonymousResourceCollection
    {
        return $this->paginated(VehicleResource::class, $action->execute($request->toPageQuery(), $request->needsMaintenance()));
    }

    public function show(string $vehicle, GetVehicleAction $action): VehicleResource
    {
        return new VehicleResource($action->execute($vehicle));
    }

    public function store(CreateVehicleRequest $request, RegisterVehicleAction $action): JsonResponse
    {
        $vehicle = $action->execute($request->toDTO());

        return (new VehicleResource($vehicle))->response()->setStatusCode(201);
    }

    public function update(UpdateVehicleRequest $request, string $vehicle, UpdateVehicleAction $action): VehicleResource
    {
        return new VehicleResource($action->execute($request->toDTO($vehicle)));
    }

    public function destroy(string $vehicle, DeleteVehicleAction $action): Response
    {
        $action->execute($vehicle);

        return response()->noContent();
    }
}
