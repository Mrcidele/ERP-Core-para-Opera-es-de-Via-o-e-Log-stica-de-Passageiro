<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Crew;

use App\Domain\Crew\Actions\ListEmployeesAction;
use App\Domain\Crew\Actions\RegisterEmployeeAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Crew\CreateEmployeeRequest;
use App\Presentation\Http\Resources\Crew\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EmployeeController extends Controller
{
    public function index(ListEmployeesAction $action): AnonymousResourceCollection
    {
        return EmployeeResource::collection($action->execute());
    }

    public function store(CreateEmployeeRequest $request, RegisterEmployeeAction $action): JsonResponse
    {
        $employee = $action->execute($request->toDTO());

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }
}
