<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Maintenance;

use App\Domain\Maintenance\Actions\CancelMaintenanceOrderAction;
use App\Domain\Maintenance\Actions\CompleteMaintenanceOrderAction;
use App\Domain\Maintenance\Actions\ExportMaintenanceOrdersAction;
use App\Domain\Maintenance\Actions\ListMaintenanceOrdersAction;
use App\Domain\Maintenance\Actions\ListMechanicsAction;
use App\Domain\Maintenance\Actions\OpenMaintenanceOrderAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Maintenance\CloseMaintenanceOrderRequest;
use App\Presentation\Http\Requests\Maintenance\ListMaintenanceOrdersRequest;
use App\Presentation\Http\Requests\Maintenance\OpenMaintenanceOrderRequest;
use App\Presentation\Http\Resources\Maintenance\MaintenanceOrderResource;
use App\Presentation\Http\Resources\Maintenance\MaintenanceOrderSummaryResource;
use App\Presentation\Http\Resources\Maintenance\MechanicResource;
use App\Presentation\Http\Support\CsvStream;
use App\Presentation\Http\Support\MaintenanceOrdersCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MaintenanceOrderController extends Controller
{
    public function index(ListMaintenanceOrdersRequest $request, ListMaintenanceOrdersAction $action): AnonymousResourceCollection
    {
        return $this->paginated(MaintenanceOrderSummaryResource::class, $action->execute($request->toPageQuery()));
    }

    public function export(ListMaintenanceOrdersRequest $request, ExportMaintenanceOrdersAction $action): StreamedResponse
    {
        return CsvStream::download(
            'ordens-de-servico-'.now()->format('Y-m-d').'.csv',
            MaintenanceOrdersCsv::HEADER,
            MaintenanceOrdersCsv::rows($action->execute($request->toPageQuery())),
        );
    }

    public function mechanics(ListMechanicsAction $action): AnonymousResourceCollection
    {
        return MechanicResource::collection($action->execute());
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
