<?php

use App\Http\Controllers\Api\V1\Platform\PlatformCadastroController;
use App\Http\Controllers\Api\V1\Gestao\GestaoEquipeController;
use App\Http\Controllers\Api\V1\Tecnicos\OperacaoTecnicaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('platform/categorias', [PlatformCadastroController::class, 'categorias']);
    Route::post('platform/cadastros/{tipo}', [PlatformCadastroController::class, 'cadastrar']);

    Route::middleware(['auth:sanctum', 'empresa'])->group(function () {
        Route::get('gestao-equipe', [GestaoEquipeController::class, 'index']);
        Route::patch('gestao-equipe/demandas/{origem}/{id}/atribuir', [GestaoEquipeController::class, 'atribuir']);
        Route::get('tecnico/operacao', [OperacaoTecnicaController::class, 'painel']);
        Route::patch('tecnico/operacao/disponibilidade', [OperacaoTecnicaController::class, 'atualizarDisponibilidade']);
        Route::patch('tecnico/operacao/localizacao', [OperacaoTecnicaController::class, 'atualizarLocalizacao']);
        Route::post('tecnico/operacao/oportunidades/{origem}/{id}/aceitar', [OperacaoTecnicaController::class, 'aceitar']);
        Route::get('platform/cadastros/mapa-equipe', [PlatformCadastroController::class, 'mapaEquipe']);
        Route::get('platform/cadastros/mapa-tecnicos', [PlatformCadastroController::class, 'mapaTecnicos']);
        Route::get('platform/cadastros', [PlatformCadastroController::class, 'painel']);
        Route::patch('platform/cadastros/{id}/analisar', [PlatformCadastroController::class, 'analisar']);
        Route::delete('platform/cadastros/{id}', [PlatformCadastroController::class, 'excluir']);
        Route::post('platform/cadastros/{id}/restaurar', [PlatformCadastroController::class, 'restaurar']);
        Route::delete('platform/cadastros/{id}/definitivo', [PlatformCadastroController::class, 'excluirDefinitivamente']);
    });
});
