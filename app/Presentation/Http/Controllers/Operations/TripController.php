<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Operations;

use App\Domain\Operations\Actions\CancelTripAction;
use App\Domain\Operations\Actions\CompleteTripAction;
use App\Domain\Operations\Actions\ListTripsAction;
use App\Domain\Operations\Actions\ScheduleTripAction;
use App\Domain\Operations\Actions\StartTripAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Operations\ScheduleTripRequest;
use App\Presentation\Http\Resources\Operations\TripResource;
use App\Presentation\Http\Resources\Operations\TripSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TripController extends Controller
{
    public function index(ListTripsAction $action): AnonymousResourceCollection
    {
        return TripSummaryResource::collection($action->execute());
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
