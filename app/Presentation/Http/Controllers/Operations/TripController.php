<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Operations;

use App\Domain\Operations\Actions\ScheduleTripAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Operations\ScheduleTripRequest;
use App\Presentation\Http\Resources\Operations\TripResource;
use Illuminate\Http\JsonResponse;

final class TripController extends Controller
{
    public function store(ScheduleTripRequest $request, ScheduleTripAction $action): JsonResponse
    {
        $trip = $action->execute($request->toDTO());

        return (new TripResource($trip))->response()->setStatusCode(201);
    }
}
