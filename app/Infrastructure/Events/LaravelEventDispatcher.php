<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Shared\Contracts\EventDispatcher;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class LaravelEventDispatcher implements EventDispatcher
{
    public function __construct(private Dispatcher $events) {}

    public function dispatch(object $event): void
    {
        $this->events->dispatch($event);
    }
}
