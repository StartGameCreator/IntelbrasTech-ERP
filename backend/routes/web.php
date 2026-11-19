<?php

use Illuminate\Support\Facades\Route;

// Este projeto é uma API pura consumida pelo frontend Vue.js separado
// (pasta /frontend) e pelo app mobile. As rotas de negócio ficam todas
// em routes/api.php; esta rota web serve só como healthcheck amigável.
Route::get('/', function () {
    return response()->json([
        'app'    => config('app.name'),
        'status' => 'ok',
        'docs'   => '/api/v1',
    ]);
});
