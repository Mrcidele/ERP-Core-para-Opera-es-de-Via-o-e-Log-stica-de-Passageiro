<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FuelLogModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $vehicle_id
 * @property string $recorded_by_user_id
 * @property int $odometer_km
 * @property int $liters_milliliters
 * @property int $total_cost_cents
 * @property string $station_name
 * @property CarbonImmutable $fueled_at
 * @property int|null $previous_odometer_km só nas consultas que calculam o abastecimento anterior
 * @property-read VehicleModel|null $vehicle
 * @property-read UserModel|null $recordedBy
 */
final class FuelLogModel extends Model
{
    /** @use HasFactory<FuelLogModelFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'fuel_logs';

    protected $fillable = [
        'id',
        'vehicle_id',
        'recorded_by_user_id',
        'odometer_km',
        'liters_milliliters',
        'total_cost_cents',
        'station_name',
        'fueled_at',
    ];

    protected static function newFactory(): FuelLogModelFactory
    {
        return FuelLogModelFactory::new();
    }

    // As relações incluem registros excluídos: o abastecimento continua apontando para eles.

    /**
     * @return BelongsTo<VehicleModel, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_id')->withTrashed();
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'recorded_by_user_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'odometer_km' => 'integer',
            'liters_milliliters' => 'integer',
            'total_cost_cents' => 'integer',
            'previous_odometer_km' => 'integer',
            'fueled_at' => 'immutable_datetime',
        ];
    }
}
