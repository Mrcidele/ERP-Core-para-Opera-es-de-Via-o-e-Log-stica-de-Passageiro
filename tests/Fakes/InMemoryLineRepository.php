<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\Operations\Entities\Line;
use App\Domain\Operations\Repositories\LineRepository;

final class InMemoryLineRepository implements LineRepository
{
    /** @var array<string, Line> */
    public array $lines = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @var list<string> linhas travadas, na ordem */
    public array $locked = [];

    /** @var list<string> */
    public array $linesWithActiveTrips = [];

    public function add(Line $line): void
    {
        $this->lines[$line->id] = $line;
    }

    public function all(): array
    {
        $lines = array_values(array_filter($this->lines, fn (Line $line): bool => ! in_array($line->id, $this->deleted, true)));

        usort($lines, fn (Line $a, Line $b): int => [$a->origin, $a->destination] <=> [$b->origin, $b->destination]);

        return $lines;
    }

    public function findById(string $id): ?Line
    {
        return $this->lines[$id] ?? null;
    }

    public function nextIdentity(): string
    {
        return 'line-'.(count($this->lines) + count($this->deleted) + 1);
    }

    public function findActive(string $id): ?Line
    {
        return in_array($id, $this->deleted, true) ? null : ($this->lines[$id] ?? null);
    }

    public function lockActive(string $id): ?Line
    {
        $this->locked[] = $id;

        return $this->findActive($id);
    }

    public function hasActiveTrips(string $id): bool
    {
        return in_array($id, $this->linesWithActiveTrips, true);
    }

    public function save(Line $line): void
    {
        $this->lines[$line->id] = $line;
    }

    public function delete(string $id): void
    {
        $this->deleted[] = $id;
    }
}
