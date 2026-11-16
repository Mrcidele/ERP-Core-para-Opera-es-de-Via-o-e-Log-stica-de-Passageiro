<?php

declare(strict_types=1);

namespace App\Domain\Fuel\Repositories;

use App\Domain\Fuel\DTOs\FuelLogSummaryDTO;
use App\Domain\Fuel\Entities\FuelLog;
use App\Domain\Shared\Pagination\Page;
use App\Domain\Shared\Pagination\PageQuery;

interface FuelLogRepository
{
    public function nextIdentity(): string;

    public function save(FuelLog $log): void;

    /** O abastecimento mais recente do veículo, pela data em que foi feito. */
    public function latestFor(string $vehicleId): ?FuelLog;

    public function findWithDetails(string $id): ?FuelLogSummaryDTO;

    /**
     * Abastecimentos, do mais recente para o mais antigo. A busca cobre a placa e o posto.
     *
     * @return Page<FuelLogSummaryDTO>
     */
    public function paginateWithDetails(PageQuery $query): Page;
}
