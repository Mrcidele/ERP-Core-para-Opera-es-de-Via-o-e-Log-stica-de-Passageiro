<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string|null $user_id
 * @property string $action
 * @property string $auditable_type
 * @property string|null $auditable_id
 * @property array<string, scalar|null>|null $old_values
 * @property array<string, scalar|null>|null $new_values
 * @property string|null $ip_address
 * @property CarbonImmutable $created_at
 * @property-read UserModel|null $user
 */
final class AuditLogModel extends Model
{
    use HasUuids;

    /** Registros de auditoria não são alterados: só existe a data de criação. */
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $fillable = [
        'id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'created_at',
    ];

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
