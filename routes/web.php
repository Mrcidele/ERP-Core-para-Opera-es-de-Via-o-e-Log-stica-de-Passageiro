<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// SPA: o Vue Router resolve as rotas no navegador. Ficam de fora:
// - a documentação da API (/docs/api, registrada pelo Scramble depois deste arquivo);
// - qualquer caminho com ponto (.env, .git/config, composer.json, banco.sqlite...): nenhuma rota do
//   SPA tem ponto, então esses pedidos recebem 404 em vez da página da aplicação.
Route::view('/{any?}', 'app')->where('any', '(?!docs/api$)[^.]*');
