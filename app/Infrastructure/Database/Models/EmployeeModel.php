<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use App\Domain\Crew\Enums\EmployeeRole;
use App\Domain\Crew\Enums\EmployeeStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EmployeeModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string $cpf
 * @property EmployeeRole $role
 * @property EmployeeStatus $status
 * @property string|null $cnh_number
 * @property string|null $cnh_category
 * @property CarbonImmutable|null $cnh_expiration_date
 */
final class EmployeeModel extends Model
{
    /** @use HasFactory<EmployeeModelFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'id',
        'name',
        'cpf',
        'role',
        'status',
        'cnh_number',
        'cnh_category',
        'cnh_expiration_date',
    ];

    protected static function newFactory(): EmployeeModelFactory
    {
        return EmployeeModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => EmployeeRole::class,
            'status' => EmployeeStatus::class,
            'cnh_expiration_date' => 'immutable_date',
        ];
    }
}
