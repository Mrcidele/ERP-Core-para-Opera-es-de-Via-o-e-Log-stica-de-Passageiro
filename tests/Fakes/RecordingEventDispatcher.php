<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Shared\Contracts\EventDispatcher;

final class RecordingEventDispatcher implements EventDispatcher
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): void
    {
        $this->events[] = $event;
    }
}
