<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Crew;

use App\Domain\Crew\Actions\RegisterEmployeeAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Crew\CreateEmployeeRequest;
use App\Presentation\Http\Resources\Crew\EmployeeResource;
use Illuminate\Http\JsonResponse;

final class EmployeeController extends Controller
{
    public function store(CreateEmployeeRequest $request, RegisterEmployeeAction $action): JsonResponse
    {
        $employee = $action->execute($request->toDTO());

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }
}
