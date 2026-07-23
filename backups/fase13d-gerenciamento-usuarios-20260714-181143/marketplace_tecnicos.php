<?php

use App\Http\Controllers\Api\V1\Platform\PlatformCadastroController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('platform/categorias', [PlatformCadastroController::class, 'categorias']);
    Route::post('platform/cadastros/{tipo}', [PlatformCadastroController::class, 'cadastrar']);

    Route::middleware(['auth:sanctum', 'empresa'])->group(function () {
        Route::get('platform/cadastros', [PlatformCadastroController::class, 'painel']);
        Route::patch('platform/cadastros/{id}/analisar', [PlatformCadastroController::class, 'analisar']);
    });
});
