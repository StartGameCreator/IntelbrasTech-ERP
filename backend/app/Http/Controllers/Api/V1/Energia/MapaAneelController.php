<?php

namespace App\Http\Controllers\Api\V1\Energia;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineEtapa;
use App\Services\Dashboard\ClienteGeolocalizacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MapaAneelController extends Controller
{
    private const DATASTORE_URL = 'https://dadosabertos.aneel.gov.br/api/3/action/datastore_search';
    private const RESOURCE_ID = '11ec447d-698d-4ab8-977f-b424d5deee6a';
    private const GD_RESOURCE_ID = 'b1bd71e7-d0ad-4214-9053-cbd58e9564a7';
    private const GD_RESOURCE_URL = 'https://dadosabertos.aneel.gov.br/dataset/5e0fafd2-21b9-4d5b-b622-40438d40aba2/resource/b1bd71e7-d0ad-4214-9053-cbd58e9564a7/download/empreendimento-geracao-distribuida.zip';
    private const PERFIL_GD_RESIDENCIAL_COMERCIAL = 'gd_residencial_comercial';
    private const PERFIL_GD_RESIDENCIAL = 'gd_residencial';
    private const PERFIL_GD_COMERCIAL = 'gd_comercial';
    private const PERFIL_GD_TODOS = 'gd_todos';
    private const PERFIL_SIGA = 'siga';
    private const UFS = [
        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG',
        'PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        if ($this->perfilBusca($request) !== self::PERFIL_SIGA) {
            return $this->indexGeracaoDistribuida($request);
        }

        $limit = min(max((int) $request->get('limit', 100), 1), 500);
        $pageSize = 100;
        $params = [
            'resource_id' => self::RESOURCE_ID,
            'limit' => $pageSize,
        ];

        if ($request->filled('busca')) {
            $params['q'] = trim((string) $request->busca);
        }

        $filters = [];
        $classe = trim((string) $request->get('classe', 'UFV'));
        if ($classe !== '') {
            $filters['SigTipoGeracao'] = strtoupper($classe);
        }
        if ($request->filled('uf')) {
            $filters['SigUFPrincipal'] = strtoupper((string) $request->uf);
        }
        if ($filters) {
            $params['filters'] = json_encode($filters, JSON_UNESCAPED_UNICODE);
        }

        $records = collect();
        $payload = [];
        $offset = 0;
        $totalRemoto = null;

        try {
            do {
                $response = Http::timeout(60)
                    ->retry(2, 1200)
                    ->acceptJson()
                    ->get(self::DATASTORE_URL, $params + ['offset' => $offset]);

                if (! $response->successful()) {
                    return response()->json([
                        'message' => 'Não foi possível consultar a base pública da ANEEL.',
                        'status' => $response->status(),
                    ], 502);
                }

                $payload = $response->json('result', []);
                $totalRemoto = (int) ($payload['total'] ?? 0);
                $lote = collect($payload['records'] ?? [])
                    ->map(fn ($record) => $this->normalizarRegistro((array) $record))
                    ->filter(fn ($item) => $this->passaFiltros($item, $request))
                    ->values();

                $records = $records->concat($lote)->values();
                $offset += $pageSize;
            } while ($records->count() < $limit && $offset < $totalRemoto);
        } catch (\Throwable) {
            if ($records->isEmpty()) {
                return response()->json([
                    'message' => 'A API pública da ANEEL não respondeu agora. Tente novamente em alguns instantes.',
                    'data' => [],
                    'total' => 0,
                ], 502);
            }
        }

        $records = $records->take($limit)->values();

        return response()->json([
            'data' => $records,
            'total' => $records->count(),
            'total_remoto' => $totalRemoto,
            'varridos' => min($offset, $totalRemoto ?? $offset),
            'fonte' => 'ANEEL - Microgeração distribuída',
            'resource_id' => self::RESOURCE_ID,
            'api' => self::DATASTORE_URL,
            'fields' => $payload['fields'] ?? [],
        ]);
    }

    public function resumo(): JsonResponse
    {
        $this->authorize('crm.view');

        if ($this->perfilBusca(request()) !== self::PERFIL_SIGA) {
            return response()->json([
                'total' => 0,
                'potencia_mw' => 0,
                'ufs' => [],
                'classes' => [],
                'fonte' => 'ANEEL - Geração distribuída residencial/comercial',
                'resource_id' => self::GD_RESOURCE_ID,
                'perfil' => $this->perfilBusca(request()),
                'message' => 'Resumo da base GD e carregado por UF/cidade para evitar baixar o arquivo nacional inteiro a cada abertura.',
            ]);
        }

        $response = Http::timeout(60)
            ->retry(2, 1200)
            ->acceptJson()
            ->get(self::DATASTORE_URL, [
                'resource_id' => self::RESOURCE_ID,
                'limit' => 1,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'total' => 0,
                'potencia_mw' => 0,
                'ufs' => [],
                'classes' => [],
                'message' => 'Resumo indisponível no momento.',
            ]);
        }

        return response()->json([
            'total' => (int) data_get($response->json(), 'result.total', 0),
            'potencia_mw' => 0,
            'ufs' => [],
            'classes' => [],
            'fonte' => 'ANEEL - Microgeração distribuída',
            'resource_id' => self::RESOURCE_ID,
        ]);
    }

    public function filtros(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        if ($this->perfilBusca($request) !== self::PERFIL_SIGA) {
            return $this->filtrosGeracaoDistribuida($request);
        }

        $uf = strtoupper(trim((string) $request->get('uf', '')));
        $classe = trim((string) $request->get('classe', 'UFV'));
        $cidades = [];
        $resultado = [
            'total_remoto' => 0,
            'varridos' => 0,
            'parcial' => false,
            'message' => null,
            'cache' => false,
        ];

        if ($uf !== '') {
            $filters = ['SigUFPrincipal' => $uf];
            if ($classe !== '') {
                $filters['SigTipoGeracao'] = strtoupper($classe);
            }

            $cacheKey = 'aneel:filtros:cidades:' . md5(json_encode($filters));
            $resultado = Cache::get($cacheKey);

            if (! is_array($resultado)) {
                $resultado = $this->coletarCidadesAneel($filters);

                if (! $resultado['parcial']) {
                    Cache::put($cacheKey, $resultado, now()->addHours(12));
                }
            } else {
                $resultado['cache'] = true;
            }

            return response()->json([
                'ufs' => self::UFS,
                'cidades' => $resultado['cidades'] ?? [],
                'total_remoto' => $resultado['total_remoto'] ?? 0,
                'varridos' => $resultado['varridos'] ?? 0,
                'parcial' => $resultado['parcial'] ?? false,
                'cache' => $resultado['cache'] ?? false,
                'message' => $resultado['message'] ?? null,
            ]);

            $coletadas = collect();
            $offset = 0;
            $limit = 100;
            $total = null;

            try {
                do {
                    $response = Http::timeout(60)
                        ->retry(2, 1200)
                        ->acceptJson()
                        ->get(self::DATASTORE_URL, [
                            'resource_id' => self::RESOURCE_ID,
                            'limit' => $limit,
                            'offset' => $offset,
                            'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
                        ]);

                    if (! $response->successful()) {
                        break;
                    }

                    $total = (int) data_get($response->json(), 'result.total', 0);
                    $coletadas = $coletadas->concat($response->json('result.records', []));
                    $offset += $limit;
                } while ($offset < $total);
            } catch (\Throwable) {
                // A API pública da ANEEL às vezes encerra conexões TLS em consultas grandes.
                // Quando isso acontecer, a tela recebe as cidades já coletadas em páginas anteriores.
            }

            $cidades = $coletadas
                ->map(fn ($record) => $this->limparMunicipio((string) $this->campo((array) $record, [
                    'NomMunicipio',
                    'DscMuninicpios',
                    'DscMunicipio',
                    'Municipio',
                    'municipio',
                ])))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        return response()->json([
            'ufs' => self::UFS,
            'cidades' => $cidades,
            'total_remoto' => $resultado['total_remoto'] ?? 0,
            'varridos' => $resultado['varridos'] ?? 0,
            'parcial' => $resultado['parcial'] ?? false,
            'cache' => $resultado['cache'] ?? false,
            'message' => $resultado['message'] ?? null,
        ]);
    }

    public function microgeracaoResidencialComercial(Request $request): JsonResponse
    {
        $request->merge(['perfil' => self::PERFIL_GD_RESIDENCIAL_COMERCIAL]);

        return $this->index($request);
    }

    public function filtrosResidencialComercial(Request $request): JsonResponse
    {
        $request->merge(['perfil' => self::PERFIL_GD_RESIDENCIAL_COMERCIAL]);

        return $this->filtros($request);
    }

    public function localizar(Request $request, ClienteGeolocalizacaoService $geolocalizacao): JsonResponse
    {
        $this->authorize('crm.view');

        $data = $request->validate([
            'cep' => ['nullable', 'string', 'max:12'],
            'municipio' => ['nullable', 'required_without:cep', 'string', 'max:180'],
            'uf' => ['nullable', 'required_without:cep', 'string', 'size:2'],
        ]);

        $cep = preg_replace('/\D/', '', (string) ($data['cep'] ?? ''));
        $municipio = trim((string) ($data['municipio'] ?? ''));
        $uf = strtoupper(trim((string) ($data['uf'] ?? '')));
        $cacheKey = 'aneel:localizacao:' . md5(implode('|', [$cep, Str::ascii($municipio), $uf]));

        $coordenadas = Cache::remember($cacheKey, now()->addDays(30), function () use ($geolocalizacao, $cep, $municipio, $uf) {
            if (strlen($cep) === 8) {
                $porCep = $geolocalizacao->consultarCep($cep);
                if ($porCep) {
                    return $porCep + ['precisao' => 'cep'];
                }
            }

            if ($municipio !== '' && $uf !== '') {
                $porMunicipio = $geolocalizacao->consultarEndereco("{$municipio}, {$uf}, Brasil");
                if ($porMunicipio) {
                    return $porMunicipio + ['precisao' => 'municipio'];
                }
            }

            return null;
        });

        if (! $coordenadas) {
            return response()->json([
                'message' => 'Não foi possível localizar este registro pelo CEP ou município informado pela ANEEL.',
            ], 422);
        }

        return response()->json([
            'latitude' => (float) $coordenadas['latitude'],
            'longitude' => (float) $coordenadas['longitude'],
            'fonte' => $coordenadas['fonte'] ?? 'geocodificacao',
            'precisao' => $coordenadas['precisao'] ?? 'aproximada',
        ]);
    }

    public function importarLead(Request $request): JsonResponse
    {
        $this->authorize('crm.create');

        $data = $request->validate([
            'codigo' => ['nullable', 'string', 'max:80'],
            'nome' => ['required', 'string', 'max:200'],
            'titular' => ['nullable', 'string', 'max:5000'],
            'uf' => ['nullable', 'string', 'max:2'],
            'municipio' => ['nullable', 'string', 'max:180'],
            'classe' => ['nullable', 'string', 'max:80'],
            'fonte' => ['nullable', 'string', 'max:180'],
            'potencia_mw' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'data_conexao' => ['nullable', 'string', 'max:80'],
            'distribuidora' => ['nullable', 'string', 'max:200'],
            'raw' => ['nullable', 'array'],
        ]);

        $empresaId = auth()->user()->empresa_id;
        $codigo = trim((string) ($data['codigo'] ?? ''));
        $cnpjs = $this->extrairCnpjs(($data['titular'] ?? '') . ' ' . json_encode($data['raw'] ?? []));
        $cnpj = $cnpjs[0] ?? null;

        $duplicado = null;
        if ($codigo !== '' || $cnpj) {
            $duplicado = Lead::where('empresa_id', $empresaId)
                ->where('origem', 'mapa_aneel')
                ->when($codigo !== '', fn ($q) => $q->where('tags', 'like', '%ANEEL:' . $codigo . '%'))
                ->when($codigo === '' && $cnpj, fn ($q) => $q->where('cnpj', $cnpj))
                ->first();
        }

        if ($duplicado) {
            return response()->json([
                'message' => 'Este registro ANEEL já está no CRM.',
                'lead' => $duplicado,
            ], 409);
        }

        $pipeline = Pipeline::where('empresa_id', $empresaId)->where('ativo', true)->first();
        $etapaId = $pipeline
            ? PipelineEtapa::where('pipeline_id', $pipeline->id)->orderBy('ordem')->value('id')
            : null;

        $lead = Lead::create([
            'empresa_id' => $empresaId,
            'uuid' => (string) Str::uuid(),
            'pipeline_id' => $pipeline?->id,
            'etapa_id' => $etapaId,
            'responsavel_id' => auth()->id(),
            'nome' => $data['nome'],
            'empresa_nome' => $data['titular'] ?: $data['nome'],
            'cnpj' => $cnpj,
            'cidade' => $data['municipio'] ?? null,
            'uf' => $data['uf'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'fonte_referencia' => 'ANEEL - Microgeração distribuída',
            'dados_extras' => json_encode([
                'origem' => 'mapa_aneel',
                'codigo_aneel' => $codigo,
                'classe' => $data['classe'] ?? null,
                'fonte' => $data['fonte'] ?? null,
                'potencia_mw' => $data['potencia_mw'] ?? null,
                'data_conexao' => $data['data_conexao'] ?? null,
                'distribuidora' => $data['distribuidora'] ?? null,
                'titular' => $data['titular'] ?? null,
                'cnpjs_detectados' => $cnpjs,
                'contato' => [
                    'email' => null,
                    'telefone' => null,
                    'observacao' => 'Contato direto não informado no dataset público da ANEEL. Enriquecer pelo CNPJ no CRM.',
                ],
                'raw_aneel' => $data['raw'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'titulo' => 'Oportunidade comercial - Microgeração ANEEL',
            'origem' => 'mapa_aneel',
            'valor_estimado' => 0,
            'probabilidade' => 10,
            'status' => Lead::STATUS_ABERTO,
            'tags' => implode(',', array_filter([
                'Mapa ANEEL',
                $data['classe'] ?? null,
                $codigo ? 'ANEEL:' . $codigo : null,
                $cnpj ? 'CNPJ:' . $cnpj : null,
                $data['uf'] ?? null,
                isset($data['potencia_mw']) ? $data['potencia_mw'] . 'MW' : null,
                'Aguardando enriquecimento',
            ])),
        ]);

        $lead = app(\App\Services\Crm\CrmLeadIntakeService::class)
            ->registrar($lead, auth()->id(), 'Lead importado do mapa ANEEL');

        return response()->json([
            'message' => 'Registro ANEEL enviado ao CRM para prospecção.',
            'lead' => $lead->fresh(['etapa', 'responsavel']),
            'cnpj_detectado' => $cnpj,
        ], 201);
    }

    private function indexGeracaoDistribuida(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->get('limit', 100), 1), 500);

        if (! $request->filled('uf') && ! $request->filled('cidade') && ! $request->filled('busca')) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'total_remoto' => 0,
                'varridos' => 0,
                'parcial' => false,
                'message' => 'Selecione um estado, cidade ou termo de busca para consultar a base grande de Geração Distribuída.',
                'fonte' => 'ANEEL - Geração distribuída',
                'resource_id' => self::GD_RESOURCE_ID,
                'perfil' => $this->perfilBusca($request),
            ]);
        }

        $resultado = $this->varrerGeracaoDistribuida($request, $limit);

        if (($resultado['erro'] ?? null) && empty($resultado['data'])) {
            return response()->json([
                'message' => $resultado['erro'],
                'data' => [],
                'total' => 0,
                'fonte' => 'ANEEL - Geração distribuída',
                'resource_id' => self::GD_RESOURCE_ID,
                'perfil' => $this->perfilBusca($request),
            ], 502);
        }

        return response()->json([
            'data' => $resultado['data'],
            'total' => count($resultado['data']),
            'total_remoto' => $resultado['total_remoto'],
            'varridos' => $resultado['varridos'],
            'parcial' => $resultado['parcial'],
            'message' => $resultado['erro'],
            'fonte' => 'ANEEL - Geração distribuída',
            'resource_id' => self::GD_RESOURCE_ID,
            'api' => self::GD_RESOURCE_URL,
            'perfil' => $this->perfilBusca($request),
        ]);
    }

    private function filtrosGeracaoDistribuida(Request $request): JsonResponse
    {
        $uf = strtoupper(trim((string) $request->get('uf', '')));
        $resultado = [
            'cidades' => [],
            'total_remoto' => 0,
            'varridos' => 0,
            'parcial' => false,
            'erro' => null,
            'cache' => false,
        ];

        if ($uf !== '') {
            $sqlite = $this->consultarCidadesGeracaoDistribuidaSqlite($request);
            if ($sqlite !== null) {
                return response()->json([
                    'ufs' => self::UFS,
                    'cidades' => $sqlite['cidades'],
                    'total_remoto' => $sqlite['total_remoto'],
                    'varridos' => $sqlite['varridos'],
                    'parcial' => false,
                    'cache' => true,
                    'message' => $sqlite['message'],
                    'fonte' => 'ANEEL - Geração distribuída',
                    'resource_id' => self::GD_RESOURCE_ID,
                    'perfil' => $this->perfilBusca($request),
                ]);
            }

            $cacheKey = 'aneel:gd:filtros:' . md5(json_encode([
                'uf' => $uf,
                'classe' => $request->get('classe', 'UFV'),
                'perfil' => $this->perfilBusca($request),
            ]));
            $resultado = Cache::get($cacheKey);

            if (! is_array($resultado)) {
                $coletor = $this->varrerGeracaoDistribuida($request, 0, true);
                $resultado = [
                    'cidades' => $coletor['cidades'],
                    'total_remoto' => $coletor['total_remoto'],
                    'varridos' => $coletor['varridos'],
                    'parcial' => $coletor['parcial'],
                    'erro' => $coletor['erro'],
                    'cache' => false,
                ];

                if (! $resultado['parcial'] && ! $resultado['erro']) {
                    Cache::put($cacheKey, $resultado, now()->addHours(12));
                }
            } else {
                $resultado['cache'] = true;
            }
        }

        return response()->json([
            'ufs' => self::UFS,
            'cidades' => $resultado['cidades'] ?? [],
            'total_remoto' => $resultado['total_remoto'] ?? 0,
            'varridos' => $resultado['varridos'] ?? 0,
            'parcial' => $resultado['parcial'] ?? false,
            'cache' => $resultado['cache'] ?? false,
            'message' => $resultado['erro'] ?? null,
            'fonte' => 'ANEEL - Geração distribuída',
            'resource_id' => self::GD_RESOURCE_ID,
            'perfil' => $this->perfilBusca($request),
        ]);
    }

    private function varrerGeracaoDistribuida(Request $request, int $limit = 100, bool $apenasCidades = false): array
    {
        if (! $apenasCidades) {
            $sqlite = $this->consultarGeracaoDistribuidaSqlite($request, $limit);
            if ($sqlite !== null) {
                return $sqlite;
            }
        }

        $data = [];
        $cidades = [];
        $varridos = 0;
        $totalFiltrado = 0;
        $parcial = false;
        $erro = null;

        try {
            $stream = $this->abrirCsvGeracaoDistribuida();
            $header = $this->lerCsv($stream);

            while (($row = $this->lerCsv($stream)) !== false) {
                if (count($row) !== count($header)) {
                    continue;
                }

                $varridos++;
                $record = array_combine($header, $row);
                if (! is_array($record)) {
                    continue;
                }

                $item = $this->normalizarRegistro($record);
                if (! $this->passaPerfilBusca($item, $request) || ! $this->passaFiltros($item, $request)) {
                    continue;
                }

                $totalFiltrado++;
                if ($apenasCidades) {
                    if (! empty($item['municipio'])) {
                        $cidades[$item['municipio']] = true;
                    }
                    continue;
                }

                if (count($data) < $limit) {
                    $data[] = $item;
                }

                if ($limit > 0 && count($data) >= $limit) {
                    break;
                }
            }

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $exception) {
            $parcial = true;
            $erro = 'Não foi possível abrir a base de Geração Distribuída da ANEEL agora. Tente novamente em alguns instantes.';
        }

        $cidades = array_keys($cidades);
        sort($cidades, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'data' => $data,
            'cidades' => $cidades,
            'total_remoto' => $totalFiltrado,
            'varridos' => $varridos,
            'parcial' => $parcial,
            'erro' => $erro,
        ];
    }

    private function consultarCidadesGeracaoDistribuidaSqlite(Request $request): ?array
    {
        $pdo = $this->abrirSqliteGeracaoDistribuida();
        if (! $pdo) {
            return null;
        }

        [$where, $params] = $this->whereGeracaoDistribuidaSqlite($request, false);
        $sql = "SELECT municipio, COUNT(*) as total FROM gd {$where} GROUP BY municipio_norm, municipio ORDER BY municipio COLLATE NOCASE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'cidades' => array_values(array_filter(array_map(fn ($row) => (string) $row['municipio'], $rows))),
            'total_remoto' => array_sum(array_map(fn ($row) => (int) $row['total'], $rows)),
            'varridos' => array_sum(array_map(fn ($row) => (int) $row['total'], $rows)),
            'message' => null,
        ];
    }

    private function consultarGeracaoDistribuidaSqlite(Request $request, int $limit): ?array
    {
        $pdo = $this->abrirSqliteGeracaoDistribuida();
        if (! $pdo) {
            return null;
        }

        [$where, $params] = $this->whereGeracaoDistribuidaSqlite($request, true);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM gd {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM gd {$where} ORDER BY potencia_mw DESC, municipio COLLATE NOCASE LIMIT :limit";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        $data = array_map(fn ($row) => $this->normalizarRegistroSqlite($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

        return [
            'data' => $data,
            'cidades' => [],
            'total_remoto' => $total,
            'varridos' => $total,
            'parcial' => false,
            'erro' => null,
        ];
    }

    private function abrirSqliteGeracaoDistribuida(): ?\PDO
    {
        $dbPath = storage_path('app/aneel/gd.sqlite');
        if (! is_file($dbPath)) {
            return null;
        }

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function whereGeracaoDistribuidaSqlite(Request $request, bool $incluirCidade): array
    {
        $where = [];
        $params = [];

        if ($request->filled('uf')) {
            $where[] = 'uf = :uf';
            $params[':uf'] = strtoupper((string) $request->uf);
        }

        if ($incluirCidade && $request->filled('cidade')) {
            $cidadeNorm = $this->normalizarTexto((string) $request->cidade);
            $cidadePrefixo = substr(preg_replace('/[^a-z0-9]/', '', $cidadeNorm), 0, 4);
            $where[] = '(municipio = :cidade_raw OR municipio_norm = :cidade OR municipio_norm LIKE :cidade_like)';
            $params[':cidade_raw'] = (string) $request->cidade;
            $params[':cidade'] = $cidadeNorm;
            $params[':cidade_like'] = $cidadePrefixo !== '' ? '%' . $cidadePrefixo . '%' : '%' . $cidadeNorm . '%';
        }

        $classe = trim((string) $request->get('classe', 'UFV'));
        if ($classe !== '') {
            $where[] = 'tipo_norm LIKE :tipo';
            $params[':tipo'] = '%' . $this->normalizarTexto($classe) . '%';
        }

        if ($request->filled('busca')) {
            $where[] = 'busca_norm LIKE :busca';
            $params[':busca'] = '%' . $this->normalizarTexto((string) $request->busca) . '%';
        }

        if ($request->filled('potencia_min')) {
            $where[] = 'potencia_mw >= :potencia_min';
            $params[':potencia_min'] = (float) $request->potencia_min;
        }

        [$cepInicio, $cepFim, $cepPrefixo] = $this->filtroCep($request);
        if ($cepInicio !== null && $cepFim !== null) {
            $where[] = 'CAST(cep AS INTEGER) BETWEEN :cep_inicio AND :cep_fim';
            $params[':cep_inicio'] = $cepInicio;
            $params[':cep_fim'] = $cepFim;
        } elseif ($cepPrefixo !== null) {
            $where[] = 'cep LIKE :cep';
            $params[':cep'] = $cepPrefixo . '%';
        }

        $perfil = $this->perfilBusca($request);
        if ($perfil === self::PERFIL_GD_RESIDENCIAL_COMERCIAL) {
            $where[] = "(classe_norm LIKE '%residencial%' OR classe_norm LIKE '%comercial%' OR classe_norm = '')";
            $where[] = "(porte_norm LIKE '%micro%' OR porte_norm = '')";
        } elseif ($perfil === self::PERFIL_GD_RESIDENCIAL) {
            $where[] = "(classe_norm LIKE '%residencial%' OR classe_norm = '')";
            $where[] = "(porte_norm LIKE '%micro%' OR porte_norm = '')";
        } elseif ($perfil === self::PERFIL_GD_COMERCIAL) {
            $where[] = "(classe_norm LIKE '%comercial%' OR classe_norm = '')";
            $where[] = "(porte_norm LIKE '%micro%' OR porte_norm = '')";
        }

        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function filtroCep(Request $request): array
    {
        $inicio = preg_replace('/\D/', '', (string) $request->get('cep_inicio', ''));
        $fim = preg_replace('/\D/', '', (string) $request->get('cep_fim', ''));

        if ($inicio !== '' && $fim !== '') {
            return [(int) str_pad($inicio, 8, '0'), (int) str_pad($fim, 8, '9'), null];
        }

        if ($inicio !== '') {
            if (strlen($inicio) >= 8) {
                return [(int) substr($inicio, 0, 8), (int) substr($inicio, 0, 8), null];
            }

            return [null, null, $inicio];
        }

        $cepLivre = (string) $request->get('cep', '');
        preg_match_all('/\d{2}\.?\d{3}-?\d{0,3}|\d+/', $cepLivre, $matches);
        $numeros = array_values(array_filter(array_map(
            fn ($value) => preg_replace('/\D/', '', $value),
            $matches[0] ?? []
        )));

        if (count($numeros) >= 2) {
            return [
                (int) str_pad($numeros[0], 8, '0'),
                (int) str_pad($numeros[1], 8, '9'),
                null,
            ];
        }

        if (count($numeros) === 1 && $numeros[0] !== '') {
            $cep = $numeros[0];
            if (strlen($cep) >= 8) {
                return [(int) substr($cep, 0, 8), (int) substr($cep, 0, 8), null];
            }

            return [null, null, $cep];
        }

        return [null, null, null];
    }

    private function normalizarRegistroSqlite(array $row): array
    {
        $documento = preg_replace('/\D/', '', (string) ($row['documento'] ?? ''));

        return [
            'id' => (string) ($row['codigo'] ?? $row['id'] ?? Str::uuid()),
            'codigo' => (string) ($row['codigo'] ?? ''),
            'nome' => (string) ($row['nome'] ?? 'Microgeração ANEEL'),
            'titular' => (string) ($row['titular'] ?? ''),
            'cnpj_principal' => strlen($documento) === 14 ? $documento : null,
            'cnpjs_detectados' => strlen($documento) === 14 ? [$documento] : [],
            'contato' => [
                'email' => null,
                'telefone' => null,
                'observacao' => 'Contato direto não informado no dataset público da ANEEL.',
            ],
            'uf' => (string) ($row['uf'] ?? ''),
            'municipio' => (string) ($row['municipio'] ?? ''),
            'classe' => (string) ($row['classe'] ?? ''),
            'classe_consumo' => (string) ($row['classe'] ?? ''),
            'porte' => (string) ($row['porte'] ?? ''),
            'tipo_geracao' => (string) ($row['tipo_geracao'] ?? ''),
            'fonte' => (string) ($row['fonte'] ?? ''),
            'cep' => (string) ($row['cep'] ?? ''),
            'potencia_mw' => (float) ($row['potencia_mw'] ?? 0),
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'data_conexao' => (string) ($row['data_conexao'] ?? ''),
            'distribuidora' => (string) ($row['distribuidora'] ?? ''),
            'raw' => $row,
        ];
    }

    private function abrirCsvGeracaoDistribuida(): mixed
    {
        $zipPath = storage_path('app/aneel/empreendimento-geracao-distribuida.zip');
        $csvPath = storage_path('app/aneel/empreendimento-geracao-distribuida.csv');
        $dir = dirname($zipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (! is_file($zipPath) || filesize($zipPath) < 1024) {
            $tmpPath = $zipPath . '.tmp';
            $response = Http::timeout(900)
                ->retry(3, 2000)
                ->withOptions(['sink' => $tmpPath])
                ->get(self::GD_RESOURCE_URL);

            if (! $response->successful()) {
                @unlink($tmpPath);
                throw new \RuntimeException('Falha ao baixar base GD.');
            }

            rename($tmpPath, $zipPath);
        }

        if (is_file($csvPath) && filemtime($csvPath) >= filemtime($zipPath)) {
            $stream = fopen($csvPath, 'rb');
            if ($stream) {
                return $stream;
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Falha ao abrir ZIP da base GD.');
        }

        $entryName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (Str::endsWith(Str::lower($name), '.csv')) {
                $entryName = $name;
                break;
            }
        }

        if (! $entryName) {
            $zip->close();
            throw new \RuntimeException('CSV da base GD nao encontrado.');
        }

        $stream = $zip->getStream($entryName);
        if (! $stream) {
            $zip->close();
            throw new \RuntimeException('Falha ao ler CSV da base GD.');
        }

        $out = fopen($csvPath, 'wb');
        if (! $out) {
            fclose($stream);
            $zip->close();
            throw new \RuntimeException('Falha ao criar cache CSV da base GD.');
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
        $zip->close();

        $cached = fopen($csvPath, 'rb');
        if (! $cached) {
            throw new \RuntimeException('Falha ao abrir cache CSV da base GD.');
        }

        return $cached;
    }

    private function lerCsv(mixed $stream): array|false
    {
        $row = fgetcsv($stream, 0, ';');
        if ($row !== false && count($row) === 1 && str_contains((string) $row[0], ',')) {
            $row = str_getcsv((string) $row[0], ',');
        }

        if (is_array($row) && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        return $row;
    }

    private function perfilBusca(Request $request): string
    {
        $perfil = (string) $request->get('perfil', self::PERFIL_GD_RESIDENCIAL_COMERCIAL);

        return in_array($perfil, [
            self::PERFIL_GD_RESIDENCIAL_COMERCIAL,
            self::PERFIL_GD_RESIDENCIAL,
            self::PERFIL_GD_COMERCIAL,
            self::PERFIL_GD_TODOS,
            self::PERFIL_SIGA,
        ], true)
            ? $perfil
            : self::PERFIL_GD_RESIDENCIAL_COMERCIAL;
    }

    private function passaPerfilBusca(array $item, Request $request): bool
    {
        $perfil = $this->perfilBusca($request);

        if (in_array($perfil, [self::PERFIL_GD_TODOS, self::PERFIL_SIGA], true)) {
            return true;
        }

        $classe = $this->normalizarTexto((string) ($item['classe_consumo'] ?? $item['classe'] ?? ''));
        $porte = $this->normalizarTexto((string) ($item['porte'] ?? ''));

        $ehResidencial = str_contains($classe, 'residencial');
        $ehComercial = str_contains($classe, 'comercial');
        $classeCompatível = match ($perfil) {
            self::PERFIL_GD_RESIDENCIAL => $classe === '' || $ehResidencial,
            self::PERFIL_GD_COMERCIAL => $classe === '' || $ehComercial,
            default => $classe === '' || $ehResidencial || $ehComercial,
        };
        $ehMicro = $porte === '' || str_contains($porte, 'micro');

        return $classeCompatível && $ehMicro;
    }

    private function passaFiltros(array $item, Request $request): bool
    {
        if ($request->filled('busca')) {
            $busca = $this->normalizarTexto(trim((string) $request->busca));
            $texto = $this->normalizarTexto(implode(' ', [
                $item['codigo'] ?? '',
                $item['nome'] ?? '',
                $item['titular'] ?? '',
                $item['municipio'] ?? '',
                $item['uf'] ?? '',
                $item['classe'] ?? '',
                $item['classe_consumo'] ?? '',
                $item['tipo_geracao'] ?? '',
                $item['porte'] ?? '',
                $item['fonte'] ?? '',
                $item['distribuidora'] ?? '',
            ]));

            if (! str_contains($texto, $busca)) {
                return false;
            }
        }

        if ($request->filled('uf') && strtoupper((string) $item['uf']) !== strtoupper((string) $request->uf)) {
            return false;
        }

        if ($request->filled('cidade')) {
            $cidade = $this->normalizarTexto((string) $request->cidade);
            if ($this->normalizarTexto((string) $item['municipio']) !== $cidade) {
                return false;
            }
        }

        $classeFiltro = trim((string) $request->get('classe', 'UFV'));
        if ($classeFiltro !== '') {
            $classe = Str::lower((string) $item['classe']);
            $tipoGeracao = Str::lower((string) ($item['tipo_geracao'] ?? ''));
            $fonte = Str::lower((string) $item['fonte']);
            $filtro = Str::lower($classeFiltro);
            if (! str_contains($classe, $filtro) && ! str_contains($tipoGeracao, $filtro) && ! str_contains($fonte, $filtro)) {
                return false;
            }
        }

        if ($request->filled('potencia_min') && (float) $item['potencia_mw'] < (float) $request->potencia_min) {
            return false;
        }

        return true;
    }

    private function normalizarRegistro(array $record): array
    {
        $titular = (string) $this->campo($record, [
            'NomTitularEmpreendimento',
            'NomAgente',
            'DscPropriRegimePariticipacao',
            'NomeTitular',
            'NomTitular',
            'Titular',
        ]);
        $cnpjs = $this->extrairCnpjs($titular . ' ' . json_encode($record));
        $potenciaKw = $this->primeiroNumeroPositivo($record, [
            'MdaPotenciaInstaladaKW',
            'MdaPotenciaInstaladaKw',
            'MdaPotenciaInstalada',
            'MdaPotenciaOutorgadaKw',
            'MdaPotenciaFiscalizadaKw',
            'PotenciaInstaladaKW',
            'PotInstaladaKW',
            'potencia_instalada_kw',
        ]);

        $latitude = $this->numero($this->campo($record, [
            'NumCoordNEmpreendimento',
            'NumCoordN',
            'Latitude',
            'latitude',
            'NumLatitude',
        ]));

        $longitude = $this->numero($this->campo($record, [
            'NumCoordEEmpreendimento',
            'NumCoordE',
            'Longitude',
            'longitude',
            'NumLongitude',
        ]));

        return [
            'id' => (string) ($record['_id'] ?? $record['CodCEG'] ?? $record['CodEmpreendimento'] ?? Str::uuid()),
            'codigo' => (string) $this->campo($record, ['CodEmpreendimento', 'CodCEG', 'cod_ceg', 'codigo', '_id']),
            'nome' => (string) ($this->campo($record, [
                'NomEmpreendimento',
                'NomTitularEmpreendimento',
                'NomeEmpreendimento',
                'nomEmpreendimento',
            ]) ?: 'Microgeração ANEEL'),
            'titular' => $titular,
            'cnpj_principal' => $cnpjs[0] ?? null,
            'cnpjs_detectados' => $cnpjs,
            'contato' => [
                'email' => null,
                'telefone' => null,
                'observacao' => 'Contato direto não informado no dataset público da ANEEL.',
            ],
            'uf' => (string) $this->campo($record, ['SigUF', 'SigUFPrincipal', 'UF', 'uf']),
            'municipio' => $this->limparMunicipio((string) $this->campo($record, [
                'NomMunicipio',
                'DscMuninicpios',
                'DscMunicipio',
                'Municipio',
                'municipio',
            ])),
            'classe' => (string) $this->campo($record, [
                'DscClasseConsumo',
                'CodClasseConsumo',
                'DscSubGrupoTarifario',
                'SigTipoGeracao',
                'DscFaseUsina',
            ]),
            'classe_consumo' => (string) $this->campo($record, [
                'DscClasseConsumo',
                'ClasseConsumo',
                'CodClasseConsumo',
            ]),
            'porte' => (string) $this->campo($record, [
                'DscPorte',
                'Porte',
                'DscPorteUsina',
            ]),
            'tipo_geracao' => (string) $this->campo($record, [
                'SigTipoGeracao',
                'TipoGeracao',
            ]),
            'fonte' => (string) ($this->campo($record, [
                'DscFonteGeracao',
                'NomFonteCombustivel',
                'SigTipoGeracao',
                'Fonte',
            ]) ?: 'Microgeração distribuída'),
            'potencia_mw' => $potenciaKw ? round($potenciaKw / 1000, 6) : 0,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'data_conexao' => (string) $this->campo($record, [
                'DthAtualizaCadastralEmpreend',
                'DatConexao',
                'DatEntradaOperacao',
                'DataConexao',
            ]),
            'distribuidora' => (string) $this->campo($record, [
                'NomAgente',
                'SigAgente',
                'NomDistribuidora',
                'NomeDistribuidora',
                'Distribuidora',
            ]),
            'raw' => $record,
        ];
    }

    private function coletarCidadesAneel(array $filters): array
    {
        $coletadas = collect();
        $offset = 0;
        $limit = 500;
        $total = null;
        $parcial = false;
        $message = null;

        try {
            do {
                $response = Http::timeout(60)
                    ->retry(2, 1200)
                    ->acceptJson()
                    ->get(self::DATASTORE_URL, [
                        'resource_id' => self::RESOURCE_ID,
                        'limit' => $limit,
                        'offset' => $offset,
                        'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
                    ]);

                if (! $response->successful()) {
                    $parcial = true;
                    $message = 'A ANEEL interrompeu a consulta antes de concluir todas as cidades.';
                    break;
                }

                $total = (int) data_get($response->json(), 'result.total', 0);
                $coletadas = $coletadas->concat($response->json('result.records', []));
                $offset += $limit;
            } while ($offset < $total);
        } catch (\Throwable) {
            $parcial = true;
            $message = 'A API publica da ANEEL oscilou durante a montagem da lista de cidades.';
        }

        $cidades = $coletadas
            ->map(fn ($record) => $this->limparMunicipio((string) $this->campo((array) $record, [
                'NomMunicipio',
                'DscMuninicpios',
                'DscMunicipio',
                'Municipio',
                'municipio',
            ])))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'cidades' => $cidades,
            'total_remoto' => (int) ($total ?? 0),
            'varridos' => min($offset, (int) ($total ?? $offset)),
            'parcial' => $parcial,
            'message' => $message,
            'cache' => false,
        ];
    }

    private function campo(array $record, array $candidatos): mixed
    {
        foreach ($candidatos as $campo) {
            if (array_key_exists($campo, $record) && $record[$campo] !== null && $record[$campo] !== '') {
                return $record[$campo];
            }
        }

        return null;
    }

    private function numero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $normalizado = str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', (string) $valor));

        return is_numeric($normalizado) ? (float) $normalizado : null;
    }

    private function primeiroNumeroPositivo(array $record, array $candidatos): ?float
    {
        $primeiroNumero = null;

        foreach ($candidatos as $campo) {
            if (! array_key_exists($campo, $record) || $record[$campo] === null || $record[$campo] === '') {
                continue;
            }

            $numero = $this->numero($record[$campo]);
            if ($numero === null) {
                continue;
            }

            $primeiroNumero ??= $numero;
            if ($numero > 0) {
                return $numero;
            }
        }

        return $primeiroNumero;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = Str::ascii($texto);
        $texto = Str::lower($texto);

        return preg_replace('/\s+/', ' ', trim($texto));
    }

    private function limparMunicipio(string $municipio): string
    {
        $municipio = trim($municipio);
        $municipio = preg_replace('/\s+-\s+[A-Z]{2}$/', '', $municipio);

        return trim((string) $municipio);
    }

    private function extrairCnpjs(?string $texto): array
    {
        preg_match_all('/\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}/', (string) $texto, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($cnpj) => preg_replace('/\D/', '', $cnpj))
            ->filter(fn ($cnpj) => strlen($cnpj) === 14)
            ->unique()
            ->values()
            ->all();
    }
}
