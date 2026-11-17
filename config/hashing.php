<?php

declare(strict_types=1);

return [

    /*
    | Senhas com Argon2id (resistente a força bruta em GPU). Os custos seguem a recomendação
    | mínima do OWASP (19 MiB, 2 iterações, 1 thread): cada verificação reserva essa memória,
    | então valores maiores derrubam o contêiner de 512 MB com poucos logins simultâneos.
    | Nos testes, ARGON_MEMORY/ARGON_TIME baixos deixam a suíte rápida.
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT'),
    ],

    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 19456),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 2),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,

];
