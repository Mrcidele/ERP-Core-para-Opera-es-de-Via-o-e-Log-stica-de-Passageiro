<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Fuel;

use App\Domain\Fuel\Actions\DeleteFuelLogAction;
use App\Domain\Fuel\Actions\ListFuelLogsAction;
use App\Domain\Fuel\Actions\RecordFuelLogAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Fuel\ListFuelLogsRequest;
use App\Presentation\Http\Requests\Fuel\RecordFuelLogRequest;
use App\Presentation\Http\Resources\Fuel\FuelLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class FuelLogController extends Controller
{
    public function index(ListFuelLogsRequest $request, ListFuelLogsAction $action): AnonymousResourceCollection
    {
        return $this->paginated(FuelLogResource::class, $action->execute($request->toPageQuery()));
    }

    public function store(RecordFuelLogRequest $request, RecordFuelLogAction $action): JsonResponse
    {
        return (new FuelLogResource($action->execute($request->toDTO())))->response()->setStatusCode(201);
    }

    public function destroy(string $log, DeleteFuelLogAction $action): Response
    {
        $action->execute($log);

        return response()->noContent();
    }
}
