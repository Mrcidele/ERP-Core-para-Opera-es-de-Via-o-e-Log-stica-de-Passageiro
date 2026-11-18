<?php

declare(strict_types=1);

namespace App\Presentation\Http\Support;

use App\Domain\Operations\DTOs\TripSummaryDTO;
use App\Domain\Operations\Enums\TripStatus;
use DateTimeZone;
use Generator;

/** Colunas do manifesto de viagens. */
final class TripManifestCsv
{
    public const HEADER = ['Rota', 'Distância (km)', 'Placa', 'Motorista', 'Partida', 'Chegada', 'Status'];

    private const STATUS_LABELS = [
        TripStatus::Scheduled->value => 'Agendada',
        TripStatus::InProgress->value => 'Em andamento',
        TripStatus::Completed->value => 'Concluída',
        TripStatus::Canceled->value => 'Cancelada',
    ];

    /**
     * @param  iterable<TripSummaryDTO>  $trips
     * @return Generator<int, list<string>>
     */
    public static function rows(iterable $trips): Generator
    {
        // Horários no fuso da operação, para o arquivo ser lido sem conversão.
        $zone = new DateTimeZone(ReportTimezone::name());

        foreach ($trips as $summary) {
            yield [
                "{$summary->lineOrigin} → {$summary->lineDestination}",
                (string) $summary->lineDistanceKm,
                $summary->vehiclePlate,
                $summary->driverName,
                $summary->trip->departureTime->setTimezone($zone)->format('d/m/Y H:i'),
                $summary->trip->arrivalTime->setTimezone($zone)->format('d/m/Y H:i'),
                self::STATUS_LABELS[$summary->trip->status->value],
            ];
        }
    }
}
