<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\LineDataDTO;
use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\LineCreated;

final readonly class CreateLineAction
{
    public function __construct(
        private LineRepository $lines,
        private EventDispatcher $events,
    ) {}

    public function execute(LineDataDTO $data): Line
    {
        $line = Line::define($this->lines->nextIdentity(), $data);

        $this->lines->save($line);
        $this->events->dispatch(new LineCreated($line->id, $line->snapshot()));

        return $line;
    }
}
