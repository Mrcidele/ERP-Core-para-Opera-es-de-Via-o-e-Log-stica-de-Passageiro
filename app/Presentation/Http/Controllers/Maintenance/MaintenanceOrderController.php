<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Maintenance;

use App\Domain\Maintenance\Actions\CancelMaintenanceOrderAction;
use App\Domain\Maintenance\Actions\CompleteMaintenanceOrderAction;
use App\Domain\Maintenance\Actions\ListMaintenanceOrdersAction;
use App\Domain\Maintenance\Actions\OpenMaintenanceOrderAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Maintenance\CloseMaintenanceOrderRequest;
use App\Presentation\Http\Requests\Maintenance\OpenMaintenanceOrderRequest;
use App\Presentation\Http\Resources\Maintenance\MaintenanceOrderResource;
use App\Presentation\Http\Resources\Maintenance\MaintenanceOrderSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MaintenanceOrderController extends Controller
{
    public function index(ListMaintenanceOrdersAction $action): AnonymousResourceCollection
    {
        return MaintenanceOrderSummaryResource::collection($action->execute());
    }

    public function store(OpenMaintenanceOrderRequest $request, OpenMaintenanceOrderAction $action): JsonResponse
    {
        $order = $action->execute($request->toDTO());

        return (new MaintenanceOrderResource($order))->response()->setStatusCode(201);
    }

    public function update(
        CloseMaintenanceOrderRequest $request,
        string $order,
        CompleteMaintenanceOrderAction $complete,
        CancelMaintenanceOrderAction $cancel,
    ): MaintenanceOrderResource {
        return new MaintenanceOrderResource(
            $request->isCompletion()
                ? $complete->execute($request->toCompletionDTO($order))
                : $cancel->execute($order),
        );
    }
}
