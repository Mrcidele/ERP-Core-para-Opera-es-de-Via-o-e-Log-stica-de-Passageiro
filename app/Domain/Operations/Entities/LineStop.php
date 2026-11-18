<?php

declare(strict_types=1);

namespace App\Domain\Operations\Entities;

/** Ponto de parada de uma rota (coleta, entrega ou passagem), na ordem em que o caminhão passa. */
final readonly class LineStop
{
    public function __construct(
        public int $sequence,
        public string $cityName,
        /** Quilômetros desde a origem da rota. */
        public int $cumulativeKm,
        /** Minutos desde a partida. */
        public int $estimatedMinutes,
    ) {}
}
