<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// SPA: o Vue Router resolve as rotas no navegador.
Route::view('/{any?}', 'app')->where('any', '.*');
