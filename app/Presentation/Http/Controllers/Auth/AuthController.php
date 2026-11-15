<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Auth;

use App\Domain\Identity\Actions\GetAuthenticatedUserAction;
use App\Domain\Identity\Actions\LoginAction;
use App\Domain\Identity\Actions\LogoutAction;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Requests\Auth\LoginRequest;
use App\Presentation\Http\Resources\Auth\AuthenticatedUserResource;
use App\Presentation\Http\Resources\Auth\AuthSessionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): AuthSessionResource
    {
        return new AuthSessionResource($action->execute($request->toDTO()));
    }

    public function logout(Request $request, LogoutAction $action): Response
    {
        $action->execute((string) $request->bearerToken());

        return response()->noContent();
    }

    public function me(Request $request, GetAuthenticatedUserAction $action): AuthenticatedUserResource
    {
        $userId = $request->user()?->getAuthIdentifier();

        return new AuthenticatedUserResource($action->execute(is_string($userId) ? $userId : ''));
    }
}
