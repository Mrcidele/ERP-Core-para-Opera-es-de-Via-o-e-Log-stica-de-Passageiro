<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use App\Domain\Identity\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\UserModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string $password
 * @property CarbonImmutable|null $deactivated_at
 */
final class UserModel extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserModelFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'users';

    /** `role` fica fora de propósito: papel não se define por atribuição em massa. */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function newFactory(): UserModelFactory
    {
        return UserModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
