<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\MaintenanceType;
use Carbon\CarbonImmutable;
use Database\Factories\MaintenanceOrderModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $vehicle_id
 * @property string $mechanic_id
 * @property MaintenanceType $type
 * @property MaintenanceStatus $status
 * @property string $description
 * @property int $cost_cents
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable|null $estimated_completion_at
 * @property CarbonImmutable|null $completed_at
 * @property-read VehicleModel|null $vehicle
 * @property-read EmployeeModel|null $mechanic
 */
final class MaintenanceOrderModel extends Model
{
    /** @use HasFactory<MaintenanceOrderModelFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'maintenance_orders';

    protected $fillable = [
        'id',
        'vehicle_id',
        'mechanic_id',
        'type',
        'status',
        'description',
        'cost_cents',
        'opened_at',
        'estimated_completion_at',
        'completed_at',
    ];

    protected static function newFactory(): MaintenanceOrderModelFactory
    {
        return MaintenanceOrderModelFactory::new();
    }

    // As relações incluem registros excluídos: a ordem continua apontando para eles.

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
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(EmployeeModel::class, 'mechanic_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'status' => MaintenanceStatus::class,
            'cost_cents' => 'integer',
            'opened_at' => 'immutable_datetime',
            'estimated_completion_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
