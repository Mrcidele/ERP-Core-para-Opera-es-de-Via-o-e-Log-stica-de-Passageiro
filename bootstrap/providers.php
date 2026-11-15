<?php

declare(strict_types=1);
use App\Infrastructure\Providers\AppServiceProvider;
use App\Infrastructure\Providers\AuthServiceProvider;
use App\Infrastructure\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    RepositoryServiceProvider::class,
];
