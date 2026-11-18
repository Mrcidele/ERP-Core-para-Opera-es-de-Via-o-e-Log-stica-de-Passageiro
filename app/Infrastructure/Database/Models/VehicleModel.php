<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use App\Domain\Fleet\Enums\VehicleStatus;
use App\Domain\Fleet\Enums\VehicleType;
use Database\Factories\VehicleModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $plate
 * @property string $chassis
 * @property int $payload_kg
 * @property VehicleType $vehicle_type
 * @property VehicleStatus $status
 * @property int $current_mileage
 * @property int|null $next_preventive_maintenance_km
 * @property int|null $min_meters_per_liter
 * @property string|null $model
 */
final class VehicleModel extends Model
{
    /** @use HasFactory<VehicleModelFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'vehicles';

    protected $fillable = [
        'id',
        'plate',
        'chassis',
        'payload_kg',
        'vehicle_type',
        'status',
        'current_mileage',
        'next_preventive_maintenance_km',
        'min_meters_per_liter',
        'model',
    ];

    protected static function newFactory(): VehicleModelFactory
    {
        return VehicleModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_kg' => 'integer',
            'vehicle_type' => VehicleType::class,
            'status' => VehicleStatus::class,
            'current_mileage' => 'integer',
            'next_preventive_maintenance_km' => 'integer',
            'min_meters_per_liter' => 'integer',
        ];
    }
}
