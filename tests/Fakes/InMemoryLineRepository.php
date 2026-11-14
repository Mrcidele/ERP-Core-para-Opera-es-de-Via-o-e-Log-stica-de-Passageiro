<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Repositories\LineRepository;

final class InMemoryLineRepository implements LineRepository
{
    /** @var array<string, Line> */
    public array $lines = [];

    public function add(Line $line): void
    {
        $this->lines[$line->id] = $line;
    }

    public function all(): array
    {
        $lines = array_values($this->lines);

        usort($lines, fn (Line $a, Line $b): int => [$a->origin, $a->destination] <=> [$b->origin, $b->destination]);

        return $lines;
    }

    public function findById(string $id): ?Line
    {
        return $this->lines[$id] ?? null;
    }

    public function exists(string $id): bool
    {
        return isset($this->lines[$id]);
    }
}
