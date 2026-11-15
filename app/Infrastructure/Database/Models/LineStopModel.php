<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $line_id
 * @property int $sequence
 * @property string $city_name
 * @property int $cumulative_km
 * @property int $estimated_minutes
 */
final class LineStopModel extends Model
{
    use HasUuids;

    protected $table = 'line_stops';

    protected $fillable = [
        'id',
        'line_id',
        'sequence',
        'city_name',
        'cumulative_km',
        'estimated_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'cumulative_km' => 'integer',
            'estimated_minutes' => 'integer',
        ];
    }
}
