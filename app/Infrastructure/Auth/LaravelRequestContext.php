<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Audit\Contracts\RequestContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;

final readonly class LaravelRequestContext implements RequestContext
{
    public function __construct(
        private AuthFactory $auth,
        private Request $request,
    ) {}

    public function userId(): ?string
    {
        $id = $this->auth->guard('sanctum')->id();

        return is_string($id) ? $id : null;
    }

    public function ipAddress(): ?string
    {
        return $this->request->ip();
    }
}
