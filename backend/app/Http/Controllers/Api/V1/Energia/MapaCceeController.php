<?php

namespace App\Http\Controllers\Api\V1\Energia;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MapaCceeController extends Controller
{
    private const CKAN_URL = 'https://dadosabertos.ccee.org.br/api/3/action';

    private const DATASETS = [
        [
            'id' => 'migracoes_acl',
            'nome' => 'Migrações ACL / varejista',
            'resource_id' => 'f7c4c7c0-2d7a-4b7e-9c6b-8f3a6c2a9d9e',
            'descricao' => 'Consumidores e unidades em processo de migração para o mercado livre.',
        ],
        [
            'id' => 'consumo_geracao',
            'nome' => 'Consumo e geração',
            'resource_id' => '',
            'descricao' => 'Cole aqui o resource_id de consumo/geração encontrado no catálogo CCEE.',
        ],
        [
            'id' => 'contratos_liquidacao',
            'nome' => 'Contratos e liquidação',
            'resource_id' => '',
            'descricao' => 'Cole aqui o resource_id de contratos, liquidação ou penalidades.',
        ],
        [
            'id' => 'mercado_varejista',
            'nome' => 'Mercado varejista',
            'resource_id' => '',
            'descricao' => 'Cole aqui o resource_id do painel/dataset varejista da CCEE.',
        ],
    ];

    public function datasets(): JsonResponse
    {
        $this->authorize('crm.view');

        return response()->json([
            'fonte' => 'CCEE - Dados Abertos',
            'api' => self::CKAN_URL,
            'datasets' => self::DATASETS,
        ]);
    }

    public function catalogo(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        $q = trim((string) $request->get('q', 'mercado livre varejista consumo geração contratos liquidação'));
        $cacheKey = 'ccee:catalogo:' . md5($q);

        $data = Cache::remember($cacheKey, now()->addHours(6), function () use ($q) {
            $response = $this->ckan('package_search', [
                'q' => $q,
                'rows' => 20,
            ]);

            return $response;
        });

        return response()->json($data);
    }

    public function consulta(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        $resourceId = trim((string) $request->get('resource_id', ''));
        if ($resourceId === '') {
            $resourceId = (string) data_get(self::DATASETS, '0.resource_id');
        }

        $limit = min(max((int) $request->get('limit', 100), 1), 1000);
        $offset = max((int) $request->get('offset', 0), 0);

        $params = [
            'resource_id' => $resourceId,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($request->filled('busca')) {
            $params['q'] = trim((string) $request->busca);
        }

        $filters = $this->filtrosCkan($request);
        if ($filters) {
            $params['filters'] = json_encode($filters, JSON_UNESCAPED_UNICODE);
        }

        $payload = $this->ckan('datastore_search', $params);
        $records = data_get($payload, 'result.records', []);
        $fields = data_get($payload, 'result.fields', []);
        $total = (int) data_get($payload, 'result.total', count($records));

        if (! ($payload['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => data_get($payload, 'error.message', 'Não foi possível consultar a API pública da CCEE.'),
                'resource_id' => $resourceId,
                'data' => [],
                'fields' => [],
                'total' => 0,
                'kpis' => $this->kpis([]),
            ], 502);
        }

        return response()->json([
            'success' => true,
            'fonte' => 'CCEE - Dados Abertos',
            'api' => self::CKAN_URL . '/datastore_search',
            'resource_id' => $resourceId,
            'data' => $records,
            'fields' => $fields,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'kpis' => $this->kpis($records),
            'sugestoes_filtros' => $this->sugestoesFiltros($records),
        ]);
    }

    private function ckan(string $action, array $params): array
    {
        try {
            $response = Http::timeout(60)
                ->retry(2, 1200)
                ->withoutVerifying()
                ->acceptJson()
                ->get(self::CKAN_URL . '/' . $action, $params);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => [
                        'message' => 'A CCEE respondeu com status ' . $response->status() . '.',
                        'status' => $response->status(),
                    ],
                ];
            }

            return $response->json() ?: ['success' => false, 'error' => ['message' => 'Resposta vazia da CCEE.']];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'error' => [
                    'message' => 'A API pública da CCEE não respondeu agora.',
                    'detail' => $exception->getMessage(),
                ],
            ];
        }
    }

    private function filtrosCkan(Request $request): array
    {
        $filters = [];
        foreach (['uf', 'estado', 'distribuidora', 'representante', 'ramo', 'status', 'mes', 'ano'] as $campo) {
            if (! $request->filled($campo)) {
                continue;
            }

            $valor = trim((string) $request->get($campo));
            foreach ($this->aliases($campo) as $alias) {
                $filters[$alias] = $valor;
            }
        }

        return $filters;
    }

    private function aliases(string $campo): array
    {
        return match ($campo) {
            'uf', 'estado' => ['UF', 'uf', 'Estado', 'estado', 'SigUF', 'sig_uf'],
            'distribuidora' => ['Distribuidora', 'distribuidora', 'AgenteDistribuicao', 'agente_distribuicao'],
            'representante' => ['Representante', 'representante', 'Varejista', 'varejista', 'AgenteVarejista'],
            'ramo' => ['RamoAtividade', 'ramo_atividade', 'Ramo de Atividade', 'Submercado'],
            'status' => ['Status', 'status', 'Situacao', 'situação', 'SituacaoMigracao'],
            'mes' => ['Mes', 'Mês', 'mes', 'mes_referencia'],
            'ano' => ['Ano', 'ano', 'AnoReferencia'],
            default => [$campo],
        };
    }

    private function kpis(array $records): array
    {
        $campos = $this->detectarCampos($records);
        $porUf = $this->top($records, $campos['uf']);
        $porRamo = $this->top($records, $campos['ramo']);
        $porDistribuidora = $this->top($records, $campos['distribuidora']);
        $porRepresentante = $this->top($records, $campos['representante']);
        $porStatus = $this->top($records, $campos['status']);
        $serie = $this->serieTemporal($records, $campos['data']);

        return [
            'registros_carregados' => count($records),
            'ufs' => count($porUf),
            'ramos' => count($porRamo),
            'distribuidoras' => count($porDistribuidora),
            'representantes' => count($porRepresentante),
            'por_uf' => $porUf,
            'por_ramo' => $porRamo,
            'por_distribuidora' => $porDistribuidora,
            'por_representante' => $porRepresentante,
            'por_status' => $porStatus,
            'serie_temporal' => $serie,
            'campos_detectados' => $campos,
        ];
    }

    private function sugestoesFiltros(array $records): array
    {
        $campos = $this->detectarCampos($records);

        return [
            'ufs' => array_keys($this->top($records, $campos['uf'], 50)),
            'ramos' => array_keys($this->top($records, $campos['ramo'], 50)),
            'distribuidoras' => array_keys($this->top($records, $campos['distribuidora'], 50)),
            'representantes' => array_keys($this->top($records, $campos['representante'], 50)),
            'status' => array_keys($this->top($records, $campos['status'], 50)),
        ];
    }

    private function detectarCampos(array $records): array
    {
        $keys = array_keys((array) ($records[0] ?? []));

        return [
            'uf' => $this->primeiroCampo($keys, ['uf', 'estado', 'siguf']),
            'ramo' => $this->primeiroCampo($keys, ['ramo', 'atividade', 'submercado', 'classe']),
            'distribuidora' => $this->primeiroCampo($keys, ['distribuidora', 'agente_distribuicao', 'concessionaria']),
            'representante' => $this->primeiroCampo($keys, ['representante', 'varejista', 'comercializador']),
            'status' => $this->primeiroCampo($keys, ['status', 'situacao', 'situação', 'etapa']),
            'data' => $this->primeiroCampo($keys, ['data', 'dt_', 'mes', 'mês', 'ano', 'referencia']),
        ];
    }

    private function primeiroCampo(array $keys, array $termos): ?string
    {
        foreach ($keys as $key) {
            $normalizado = Str::lower(Str::ascii((string) $key));
            foreach ($termos as $termo) {
                if (str_contains($normalizado, Str::lower(Str::ascii($termo)))) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    private function top(array $records, ?string $campo, int $limit = 10): array
    {
        if (! $campo) {
            return [];
        }

        $counts = [];
        foreach ($records as $record) {
            $valor = trim((string) data_get($record, $campo, ''));
            if ($valor === '') {
                continue;
            }
            $counts[$valor] = ($counts[$valor] ?? 0) + 1;
        }

        arsort($counts);

        return array_slice($counts, 0, $limit, true);
    }

    private function serieTemporal(array $records, ?string $campo): array
    {
        if (! $campo) {
            return [];
        }

        $counts = [];
        foreach ($records as $record) {
            $valor = trim((string) data_get($record, $campo, ''));
            if ($valor === '') {
                continue;
            }

            $chave = preg_match('/\d{4}[-\/]\d{1,2}/', $valor, $match)
                ? $match[0]
                : substr($valor, 0, 10);

            $counts[$chave] = ($counts[$chave] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
