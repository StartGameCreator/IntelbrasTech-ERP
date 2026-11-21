<?php

use App\Http\Controllers\Api\V1\Platform\PlatformCadastroController;
use App\Http\Controllers\Api\V1\Gestao\GestaoEquipeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('platform/categorias', [PlatformCadastroController::class, 'categorias']);
    Route::post('platform/cadastros/{tipo}', [PlatformCadastroController::class, 'cadastrar']);

    Route::middleware(['auth:sanctum', 'empresa'])->group(function () {
        Route::get('gestao-equipe', [GestaoEquipeController::class, 'index']);
        Route::patch('gestao-equipe/demandas/{origem}/{id}/atribuir', [GestaoEquipeController::class, 'atribuir']);
        Route::get('platform/cadastros/mapa-equipe', [PlatformCadastroController::class, 'mapaEquipe']);
        Route::get('platform/cadastros/mapa-tecnicos', [PlatformCadastroController::class, 'mapaTecnicos']);
        Route::get('platform/cadastros', [PlatformCadastroController::class, 'painel']);
        Route::patch('platform/cadastros/{id}/analisar', [PlatformCadastroController::class, 'analisar']);
        Route::delete('platform/cadastros/{id}', [PlatformCadastroController::class, 'excluir']);
        Route::post('platform/cadastros/{id}/restaurar', [PlatformCadastroController::class, 'restaurar']);
        Route::delete('platform/cadastros/{id}/definitivo', [PlatformCadastroController::class, 'excluirDefinitivamente']);
    });
});
