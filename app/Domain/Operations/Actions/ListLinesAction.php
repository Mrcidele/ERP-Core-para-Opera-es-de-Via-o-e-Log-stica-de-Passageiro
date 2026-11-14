<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Repositories\LineRepository;

final readonly class ListLinesAction
{
    public function __construct(
        private LineRepository $lines,
    ) {}

    /**
     * @return list<Line>
     */
    public function execute(): array
    {
        return $this->lines->all();
    }
}
