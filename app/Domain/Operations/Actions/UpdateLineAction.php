<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DTOs\LineDataDTO;
use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Exceptions\LineRecordNotFoundException;
use App\Domain\Operations\Repositories\LineRepository;
use App\Domain\Shared\Contracts\EventDispatcher;
use App\Domain\Shared\Events\LineUpdated;

final readonly class UpdateLineAction
{
    public function __construct(
        private LineRepository $lines,
        private EventDispatcher $events,
    ) {}

    public function execute(string $id, LineDataDTO $data): Line
    {
        $current = $this->lines->findActive($id) ?? throw new LineRecordNotFoundException($id);
        $line = Line::define($current->id, $data);

        $this->lines->save($line);
        $this->events->dispatch(new LineUpdated($line->id, $current->snapshot(), $line->snapshot()));

        return $line;
    }
}
