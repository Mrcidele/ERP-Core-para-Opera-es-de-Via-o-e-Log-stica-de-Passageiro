<?php

declare(strict_types=1);

namespace App\Domain\Maintenance\Entities;

use App\Domain\Maintenance\DTOs\OpenMaintenanceOrderDTO;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\MaintenanceType;
use App\Domain\Maintenance\Exceptions\InvalidEstimatedCompletionException;
use App\Domain\Maintenance\Exceptions\InvalidMaintenanceTransitionException;
use App\Domain\Maintenance\Exceptions\NegativeMaintenanceCostException;
use DateTimeImmutable;

final readonly class MaintenanceOrder
{
    public function __construct(
        public string $id,
        public string $vehicleId,
        public string $mechanicId,
        public MaintenanceType $type,
        public MaintenanceStatus $status,
        public string $description,
        public int $costCents,
        public DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $completedAt,
        /** Previsão de o veículo sair da oficina; null quando não informada. */
        public ?DateTimeImmutable $estimatedCompletionAt = null,
    ) {}

    public static function open(string $id, OpenMaintenanceOrderDTO $data, DateTimeImmutable $now): self
    {
        if ($data->estimatedCompletionAt !== null && $data->estimatedCompletionAt <= $now) {
            throw new InvalidEstimatedCompletionException;
        }

        return new self(
            id: $id,
            vehicleId: $data->vehicleId,
            mechanicId: $data->mechanicId,
            type: $data->type,
            status: MaintenanceStatus::Open,
            description: $data->description,
            costCents: 0,
            openedAt: $now,
            completedAt: null,
            estimatedCompletionAt: $data->estimatedCompletionAt,
        );
    }

    public function complete(int $costCents, DateTimeImmutable $now): self
    {
        if (! $this->status->isActive()) {
            throw new InvalidMaintenanceTransitionException('Só é possível concluir uma ordem aberta ou em andamento.');
        }

        if ($costCents < 0) {
            throw new NegativeMaintenanceCostException;
        }

        return new self(
            $this->id,
            $this->vehicleId,
            $this->mechanicId,
            $this->type,
            MaintenanceStatus::Completed,
            $this->description,
            $costCents,
            $this->openedAt,
            $now,
            $this->estimatedCompletionAt,
        );
    }

    public function cancel(): self
    {
        if (! $this->status->isActive()) {
            throw new InvalidMaintenanceTransitionException('Só é possível cancelar uma ordem aberta ou em andamento.');
        }

        return new self(
            $this->id,
            $this->vehicleId,
            $this->mechanicId,
            $this->type,
            MaintenanceStatus::Canceled,
            $this->description,
            $this->costCents,
            $this->openedAt,
            $this->completedAt,
            $this->estimatedCompletionAt,
        );
    }
}
