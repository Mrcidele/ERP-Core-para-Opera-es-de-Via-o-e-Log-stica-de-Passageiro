<?php

declare(strict_types=1);

/*
| O SPA é servido pela mesma origem da API e se autentica por token (Bearer), sem cookies:
| nenhuma origem externa precisa de CORS. Para liberar uma, informe-a em CORS_ALLOWED_ORIGINS
| (separadas por vírgula). Credenciais (cookies) nunca são aceitas entre origens.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 600,

    'supports_credentials' => false,

];
