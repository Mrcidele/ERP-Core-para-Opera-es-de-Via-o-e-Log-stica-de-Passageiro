<?php

declare(strict_types=1);

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
});

function inEnvironment(string $environment, bool $enabled = false): void
{
    app()->detectEnvironment(fn (): string => $environment);
    config(['scramble.enabled' => $enabled]);
}

it('serves the interactive documentation at /docs/api, not the SPA', function (): void {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('ERP Viação - API Docs')
        ->assertSee('elements-api', false)
        ->assertDontSee('<div id="app">', false);
});

it('serves the OpenAPI document of the REST API', function (): void {
    $document = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($document['openapi'])->toStartWith('3.')
        ->and($document['info']['title'])->toBe('ERP Viação')
        ->and(array_keys($document['paths']))->toContain(
            '/v1/fuel/logs',
            '/v1/fuel/logs/{log}',
            '/v1/operations/schedule-timeline',
            '/v1/maintenance/orders',
            '/v1/health',
        );
});

it('documents the bearer token on protected routes and leaves login and health check public', function (): void {
    $document = $this->getJson('/docs/api.json')->assertOk()->json();

    expect($document['security'])->toBe([['http' => []]])
        ->and($document['components']['securitySchemes']['http'])->toMatchArray(['type' => 'http', 'scheme' => 'bearer'])
        ->and($document['paths']['/v1/auth/login']['post']['security'])->toBe([])
        ->and($document['paths']['/v1/health']['get']['security'])->toBe([])
        ->and($document['paths']['/v1/fuel/logs']['get'])->not->toHaveKey('security');
});

it('documents the new fields of this delivery', function (): void {
    $schemas = $this->getJson('/docs/api.json')->assertOk()->json('components.schemas');

    expect(array_keys($schemas['OpenMaintenanceOrderRequest']['properties']))->toContain('estimated_completion_at')
        ->and(array_keys($schemas['MaintenanceOrderResource']['properties']))->toContain('estimated_completion_at')
        ->and(array_keys($schemas['FuelLogResource']['properties']))->toContain('km_per_liter', 'low_efficiency_alert');
});

it('opens the documentation without signing in', function (): void {
    $this->app['auth']->forgetGuards();

    $this->get('/docs/api')->assertOk();
    $this->getJson('/docs/api.json')->assertOk();
});

it('is open in local and testing', function (string $environment): void {
    inEnvironment($environment);

    $this->get('/docs/api')->assertOk();
})->with(['local', 'testing']);

it('answers 404 in production unless SCRAMBLE_ENABLED is true', function (string $environment): void {
    inEnvironment($environment);

    $this->get('/docs/api')->assertNotFound();
    $this->getJson('/docs/api.json')->assertNotFound();

    inEnvironment($environment, enabled: true);

    $this->get('/docs/api')->assertOk();
    $this->getJson('/docs/api.json')->assertOk();
})->with(['production', 'staging']);

it('keeps the SPA answering every other path, including ones that start like the docs', function (string $path): void {
    // No CI o Pest roda antes do build do frontend: a página do SPA não depende do manifesto do Vite aqui.
    $this->withoutVite();

    $this->get($path)->assertOk()->assertSee('<div id="app">', false)->assertDontSee('API Docs');
})->with(['/', '/frota', '/abastecimento', '/docs', '/docs/apis', '/docs/api/extra']);
