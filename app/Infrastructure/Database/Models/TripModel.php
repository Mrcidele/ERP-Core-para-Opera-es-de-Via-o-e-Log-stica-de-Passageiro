<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use App\Domain\Operations\Enums\TripStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $line_id
 * @property string $vehicle_id
 * @property string $driver_id
 * @property CarbonImmutable $departure_time
 * @property CarbonImmutable $arrival_time
 * @property TripStatus $status
 * @property-read LineModel|null $line
 * @property-read VehicleModel|null $vehicle
 * @property-read EmployeeModel|null $driver
 */
final class TripModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'trips';

    protected $fillable = [
        'id',
        'line_id',
        'vehicle_id',
        'driver_id',
        'departure_time',
        'arrival_time',
        'status',
    ];

    // As relações incluem registros excluídos: a viagem continua apontando para eles.

    /**
     * @return BelongsTo<LineModel, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(LineModel::class, 'line_id')->withTrashed();
    }

    /**
     * @return BelongsTo<VehicleModel, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_id')->withTrashed();
    }

    /**
     * @return BelongsTo<EmployeeModel, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(EmployeeModel::class, 'driver_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_time' => 'immutable_datetime',
            'arrival_time' => 'immutable_datetime',
            'status' => TripStatus::class,
        ];
    }
}
