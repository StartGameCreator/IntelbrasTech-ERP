<?php

namespace App\Http\Controllers\Api\V1\Configuracoes;

use App\Http\Controllers\Controller;
use App\Services\Empresa\EmpresaDataService;
use Illuminate\Http\JsonResponse;
use Throwable;

class EmpresaLookupController extends Controller
{
    public function cnpj(string $cnpj, EmpresaDataService $service): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Dados empresariais carregados.',
                'data' => $service->consultarCnpj($cnpj),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cep(string $cep, EmpresaDataService $service): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Endereço carregado.',
                'data' => $service->consultarCep($cep),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
