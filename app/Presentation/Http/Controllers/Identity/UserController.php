<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Identity;

use App\Domain\Identity\Actions\CreateUserAction;
use App\Domain\Identity\Actions\DeactivateUserAction;
use App\Domain\Identity\Actions\ListUsersAction;
use App\Domain\Identity\Actions\ReactivateUserAction;
use App\Domain\Identity\Actions\UpdateUserAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Identity\CreateUserRequest;
use App\Presentation\Http\Requests\Identity\ListUsersRequest;
use App\Presentation\Http\Requests\Identity\UpdateUserRequest;
use App\Presentation\Http\Resources\Identity\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserController extends Controller
{
    public function index(ListUsersRequest $request, ListUsersAction $action): AnonymousResourceCollection
    {
        return $this->paginated(UserResource::class, $action->execute($request->toPageQuery()));
    }

    public function store(CreateUserRequest $request, CreateUserAction $action): JsonResponse
    {
        return (new UserResource($action->execute($request->toDTO(), 'web')))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, string $user, UpdateUserAction $action): UserResource
    {
        return new UserResource($action->execute($request->toDTO($user)));
    }

    public function deactivate(Request $request, string $user, DeactivateUserAction $action): UserResource
    {
        $actorId = $request->user()?->getAuthIdentifier();

        return new UserResource($action->execute($user, is_string($actorId) ? $actorId : ''));
    }

    public function reactivate(string $user, ReactivateUserAction $action): UserResource
    {
        return new UserResource($action->execute($user));
    }
}
