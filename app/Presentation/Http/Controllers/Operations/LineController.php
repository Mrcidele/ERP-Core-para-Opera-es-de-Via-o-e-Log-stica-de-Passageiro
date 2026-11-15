<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Operations;

use App\Domain\Operations\Actions\CreateLineAction;
use App\Domain\Operations\Actions\DeleteLineAction;
use App\Domain\Operations\Actions\ListLinesAction;
use App\Domain\Operations\Actions\UpdateLineAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Operations\SaveLineRequest;
use App\Presentation\Http\Resources\Operations\LineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class LineController extends Controller
{
    public function index(ListLinesAction $action): AnonymousResourceCollection
    {
        return LineResource::collection($action->execute());
    }

    public function store(SaveLineRequest $request, CreateLineAction $action): JsonResponse
    {
        return (new LineResource($action->execute($request->toDTO())))->response()->setStatusCode(201);
    }

    public function update(SaveLineRequest $request, string $line, UpdateLineAction $action): LineResource
    {
        return new LineResource($action->execute($line, $request->toDTO()));
    }

    public function destroy(string $line, DeleteLineAction $action): Response
    {
        $action->execute($line);

        return response()->noContent();
    }
}
