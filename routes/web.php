<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// SPA: o Vue Router resolve as rotas no navegador. A documentação da API (/docs/api e /docs/api.json,
// registradas pelo Scramble depois deste arquivo) fica de fora, senão a página do SPA responderia por ela.
Route::view('/{any?}', 'app')->where('any', '(?!docs/api(?:\.json)?$).*');
