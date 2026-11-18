<?php

declare(strict_types=1);

namespace App\Domain\Operations\Entities;

use App\Domain\Operations\DTOs\LineDataDTO;
use App\Domain\Operations\Exceptions\InvalidLineException;
use App\Domain\Operations\Exceptions\InvalidLineStopsException;

final readonly class Line
{
    /**
     * @param  list<LineStop>  $stops  paradas intermediárias, em ordem de passagem
     */
    public function __construct(
        public string $id,
        public string $origin,
        public string $destination,
        public int $distanceKm,
        public int $estimatedDurationMinutes,
        public array $stops = [],
    ) {}

    /**
     * Monta a rota a partir dos dados do formulário. A sequência das paradas é a ordem em que
     * elas chegam; cada parada fica depois da anterior e antes do destino, em distância e em tempo.
     */
    public static function define(string $id, LineDataDTO $data): self
    {
        if (mb_strtolower($data->origin) === mb_strtolower($data->destination)) {
            throw new InvalidLineException('destination', 'O destino deve ser diferente da origem.');
        }

        $stops = [];
        $previousKm = 0;
        $previousMinutes = 0;

        foreach ($data->stops as $index => $stop) {
            $position = $index + 1;

            if ($stop->cumulativeKm <= $previousKm) {
                throw new InvalidLineStopsException("A parada {$position} ({$stop->cityName}) deve ficar depois da parada anterior: informe um km acumulado maior que {$previousKm}.");
            }

            if ($stop->cumulativeKm >= $data->distanceKm) {
                throw new InvalidLineStopsException("A parada {$position} ({$stop->cityName}) deve ficar antes do destino: o km acumulado precisa ser menor que {$data->distanceKm}.");
            }

            if ($stop->estimatedMinutes < $previousMinutes || $stop->estimatedMinutes >= $data->estimatedDurationMinutes) {
                throw new InvalidLineStopsException("O tempo até a parada {$position} ({$stop->cityName}) deve ficar entre {$previousMinutes} min e a duração da rota.");
            }

            $stops[] = new LineStop($position, $stop->cityName, $stop->cumulativeKm, $stop->estimatedMinutes);
            $previousKm = $stop->cumulativeKm;
            $previousMinutes = $stop->estimatedMinutes;
        }

        return new self($id, $data->origin, $data->destination, $data->distanceKm, $data->estimatedDurationMinutes, $stops);
    }

    /**
     * Retrato da rota para a trilha de auditoria, só com valores simples.
     *
     * @return array{origin: string, destination: string, distance_km: int, estimated_duration_minutes: int, stops: string}
     */
    public function snapshot(): array
    {
        return [
            'origin' => $this->origin,
            'destination' => $this->destination,
            'distance_km' => $this->distanceKm,
            'estimated_duration_minutes' => $this->estimatedDurationMinutes,
            'stops' => implode(' → ', array_map(fn (LineStop $stop): string => "{$stop->cityName} (km {$stop->cumulativeKm}, {$stop->estimatedMinutes} min)", $this->stops)),
        ];
    }
}
