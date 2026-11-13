<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Shared\Contracts\Clock;
use App\Infrastructure\Support\SystemClock;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        Clock::class => SystemClock::class,
    ];
}
