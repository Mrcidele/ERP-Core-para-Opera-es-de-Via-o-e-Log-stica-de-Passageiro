<?php

declare(strict_types=1);

namespace App\Presentation\Http\Support;

use App\Domain\Maintenance\DTOs\MaintenanceOrderSummaryDTO;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\MaintenanceType;
use DateTimeZone;
use Generator;

/** Colunas do relatório financeiro de ordens de serviço. */
final class MaintenanceOrdersCsv
{
    public const HEADER = ['Placa', 'Mecânico', 'Tipo', 'Descrição', 'Aberta em', 'Concluída em', 'Custo (R$)', 'Status'];

    private const TYPE_LABELS = [
        MaintenanceType::Preventive->value => 'Preventiva',
        MaintenanceType::Corrective->value => 'Corretiva',
    ];

    private const STATUS_LABELS = [
        MaintenanceStatus::Open->value => 'Aberta',
        MaintenanceStatus::InProgress->value => 'Em andamento',
        MaintenanceStatus::Completed->value => 'Concluída',
        MaintenanceStatus::Canceled->value => 'Cancelada',
    ];

    /**
     * @param  iterable<MaintenanceOrderSummaryDTO>  $orders
     * @return Generator<int, list<string>>
     */
    public static function rows(iterable $orders): Generator
    {
        $zone = new DateTimeZone(ReportTimezone::name());

        foreach ($orders as $summary) {
            $order = $summary->order;

            yield [
                $summary->vehiclePlate,
                $summary->mechanicName,
                self::TYPE_LABELS[$order->type->value],
                $order->description,
                $order->openedAt->setTimezone($zone)->format('d/m/Y H:i'),
                $order->completedAt?->setTimezone($zone)->format('d/m/Y H:i') ?? '',
                // Reais com vírgula decimal e sem separador de milhar, para a planilha somar a coluna.
                number_format($order->costCents / 100, 2, ',', ''),
                self::STATUS_LABELS[$order->status->value],
            ];
        }
    }
}
