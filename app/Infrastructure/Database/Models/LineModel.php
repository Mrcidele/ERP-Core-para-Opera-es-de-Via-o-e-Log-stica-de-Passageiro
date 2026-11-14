<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Models;

use Database\Factories\LineModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $origin
 * @property string $destination
 * @property int $distance_km
 * @property int $estimated_duration_minutes
 */
final class LineModel extends Model
{
    /** @use HasFactory<LineModelFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $table = 'lines';

    protected $fillable = [
        'id',
        'origin',
        'destination',
        'distance_km',
        'estimated_duration_minutes',
    ];

    protected static function newFactory(): LineModelFactory
    {
        return LineModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance_km' => 'integer',
            'estimated_duration_minutes' => 'integer',
        ];
    }
}
