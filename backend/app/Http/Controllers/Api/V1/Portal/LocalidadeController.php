<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Cache, Http};
use Illuminate\Support\Str;
use Throwable;

class LocalidadeController extends Controller
{
    private const IBGE_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades';

    public function ufs(): JsonResponse
    {
        try {
            $ufs = Cache::remember('portal:ibge:ufs', now()->addDays(7), fn () =>
                Http::retry(2, 250)->timeout(10)->get(self::IBGE_URL . '/estados')->throw()->collect()
                    ->map(fn ($uf) => ['id' => $uf['id'], 'sigla' => $uf['sigla'], 'nome' => $uf['nome']])
                    ->sortBy('nome')->values()->all()
            );
            return response()->json(['data' => $ufs, 'fonte' => 'IBGE']);
        } catch (Throwable) {
            return response()->json(['message' => 'Não foi possível consultar as UFs no IBGE.'], 503);
        }
    }

    public function municipios(string $uf): JsonResponse
    {
        $uf = strtoupper(trim($uf));
        abort_unless(preg_match('/^[A-Z]{2}$/', $uf), 422, 'UF inválida.');

        try {
            $municipios = Cache::remember("portal:ibge:municipios:{$uf}", now()->addDays(7), fn () =>
                Http::retry(2, 250)->timeout(12)->get(self::IBGE_URL . "/estados/{$uf}/municipios")->throw()->collect()
                    ->map(fn ($cidade) => ['id' => (string) $cidade['id'], 'nome' => $cidade['nome']])
                    ->sortBy('nome')->values()->all()
            );
            return response()->json(['data' => $municipios, 'uf' => $uf, 'fonte' => 'IBGE']);
        } catch (Throwable) {
            return response()->json(['message' => 'Não foi possível consultar os municípios no IBGE.'], 503);
        }
    }

    public function cep(string $cep): JsonResponse
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return response()->json(['message' => 'CEP inválido. Informe exatamente 8 dígitos.'], 422);
        }

        try {
            $endereco = Cache::remember("portal:cep:{$cep}", now()->addDays(30), fn () =>
                Http::retry(2, 250)->timeout(10)->get("https://viacep.com.br/ws/{$cep}/json/")->throw()->json()
            );
        } catch (Throwable) {
            return response()->json(['message' => 'O serviço de CEP está temporariamente indisponível.'], 503);
        }

        if (! is_array($endereco) || ($endereco['erro'] ?? false)) {
            return response()->json(['message' => 'CEP não encontrado.'], 404);
        }

        $ibgeValidado = false;
        if (! empty($endereco['ibge'])) {
            try {
                $municipio = Cache::remember('portal:ibge:municipio:' . $endereco['ibge'], now()->addDays(30), fn () =>
                    Http::retry(1, 200)->timeout(8)->get(self::IBGE_URL . '/municipios/' . $endereco['ibge'])->throw()->json()
                );
                $ibgeValidado = isset($municipio['nome'])
                    && Str::lower(Str::ascii($municipio['nome'])) === Str::lower(Str::ascii($endereco['localidade'] ?? ''))
                    && strtoupper($municipio['microrregiao']['mesorregiao']['UF']['sigla'] ?? '') === strtoupper($endereco['uf'] ?? '');
            } catch (Throwable) {
                // CEP continua válido pelo serviço postal; a indisponibilidade do IBGE é informada no retorno.
            }
        }

        return response()->json([
            'data' => [
                'cep' => $endereco['cep'] ?? $cep,
                'logradouro' => $endereco['logradouro'] ?? '',
                'complemento' => $endereco['complemento'] ?? '',
                'bairro' => $endereco['bairro'] ?? '',
                'cidade' => $endereco['localidade'] ?? '',
                'uf' => $endereco['uf'] ?? '',
                'ibge' => (string) ($endereco['ibge'] ?? ''),
                'ddd' => $endereco['ddd'] ?? '',
                'ibge_validado' => $ibgeValidado,
            ],
            'fonte_cep' => 'ViaCEP',
            'fonte_localidade' => 'IBGE',
        ]);
    }
}
