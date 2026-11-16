<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests\Fleet;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Presentation\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

final class ListVehiclesRequest extends ListRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'needs_maintenance' => ['sometimes', Rule::in(['0', '1', 'true', 'false'])]];
    }

    /** Filtro "revisão vencida": só veículos que já alcançaram a quilometragem da próxima preventiva. */
    public function needsMaintenance(): bool
    {
        return $this->boolean('needs_maintenance');
    }

    protected function statuses(): array
    {
        return array_column(VehicleStatus::cases(), 'value');
    }
}
