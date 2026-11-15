<?php

declare(strict_types=1);

use App\Domain\Shared\Contracts\DatabaseProbe;

it('reports the application and the database as healthy, without authentication', function (): void {
    $this->travelTo('2026-11-15 09:30:00');

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['status' => 'ok', 'database' => 'up', 'timestamp' => '2026-11-15T09:30:00+00:00'])
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('answers 503 when the database does not respond', function (): void {
    $this->travelTo('2026-11-15 09:30:00');
    $this->app->bind(DatabaseProbe::class, fn (): DatabaseProbe => new class implements DatabaseProbe
    {
        public function isReachable(): bool
        {
            return false;
        }
    });

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'unavailable', 'database' => 'down', 'timestamp' => '2026-11-15T09:30:00+00:00']);
});

it('detects a broken database connection', function (): void {
    config(['database.connections.broken' => [...config('database.connections.pgsql'), 'host' => '127.0.0.1', 'port' => 1]]);
    config(['database.default' => 'broken']);

    try {
        expect(app(DatabaseProbe::class)->isReachable())->toBeFalse();
    } finally {
        config(['database.default' => 'pgsql']);
    }
});
