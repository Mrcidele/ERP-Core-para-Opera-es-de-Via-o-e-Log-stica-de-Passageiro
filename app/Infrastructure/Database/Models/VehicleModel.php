<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use App\Domain\Fleet\Enums\VehicleClass;
use App\Domain\Fleet\Enums\VehicleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $plate
 * @property string $chassis
 * @property int $capacity
 * @property VehicleClass $class_type
 * @property VehicleStatus $status
 * @property int $current_mileage
 */
final class VehicleModel extends Model
{
    use HasUuids;

    protected $table = 'vehicles';

    protected $fillable = [
        'id',
        'plate',
        'chassis',
        'capacity',
        'class_type',
        'status',
        'current_mileage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'class_type' => VehicleClass::class,
            'status' => VehicleStatus::class,
            'current_mileage' => 'integer',
        ];
    }
}
