<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Crew;

use App\Domain\Crew\Actions\ListEmployeesAction;
use App\Domain\Crew\Actions\RegisterEmployeeAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Crew\CreateEmployeeRequest;
use App\Presentation\Http\Requests\Crew\ListEmployeesRequest;
use App\Presentation\Http\Resources\Crew\EmployeeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EmployeeController extends Controller
{
    public function index(ListEmployeesRequest $request, ListEmployeesAction $action): AnonymousResourceCollection
    {
        return $this->paginated(EmployeeResource::class, $action->execute($request->toPageQuery()));
    }

    public function store(CreateEmployeeRequest $request, RegisterEmployeeAction $action): JsonResponse
    {
        $employee = $action->execute($request->toDTO());

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }
}
