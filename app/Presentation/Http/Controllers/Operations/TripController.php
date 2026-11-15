<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Operations;

use App\Domain\Operations\Actions\CancelTripAction;
use App\Domain\Operations\Actions\CompleteTripAction;
use App\Domain\Operations\Actions\ExportTripsAction;
use App\Domain\Operations\Actions\ListTripsAction;
use App\Domain\Operations\Actions\ScheduleTripAction;
use App\Domain\Operations\Actions\StartTripAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Operations\ListTripsRequest;
use App\Presentation\Http\Requests\Operations\ScheduleTripRequest;
use App\Presentation\Http\Resources\Operations\TripResource;
use App\Presentation\Http\Resources\Operations\TripSummaryResource;
use App\Presentation\Http\Support\CsvStream;
use App\Presentation\Http\Support\TripManifestCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TripController extends Controller
{
    public function index(ListTripsRequest $request, ListTripsAction $action): AnonymousResourceCollection
    {
        return $this->paginated(TripSummaryResource::class, $action->execute($request->toPageQuery()));
    }

    public function export(ListTripsRequest $request, ExportTripsAction $action): StreamedResponse
    {
        return CsvStream::download(
            'manifesto-de-viagens-'.now()->format('Y-m-d').'.csv',
            TripManifestCsv::HEADER,
            TripManifestCsv::rows($action->execute($request->toPageQuery())),
        );
    }

    public function store(ScheduleTripRequest $request, ScheduleTripAction $action): JsonResponse
    {
        $trip = $action->execute($request->toDTO());

        return (new TripResource($trip))->response()->setStatusCode(201);
    }

    public function start(string $trip, StartTripAction $action): TripResource
    {
        return new TripResource($action->execute($trip));
    }

    public function complete(string $trip, CompleteTripAction $action): TripResource
    {
        return new TripResource($action->execute($trip));
    }

    public function cancel(string $trip, CancelTripAction $action): TripResource
    {
        return new TripResource($action->execute($trip));
    }
}
