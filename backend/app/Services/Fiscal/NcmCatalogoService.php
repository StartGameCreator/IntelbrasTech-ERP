<?php

namespace App\Services\Fiscal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NcmCatalogoService
{
    /**
     * Fonte pública do Sistema Classif/Siscomex.
     * Pode ser sobrescrita no .env por NCM_CLASSIF_URL.
     */
    private array $fontes = [
        'https://portalunico.siscomex.gov.br/classif/api/publico/nomenclatura/download/json',
        'https://portalunico.siscomex.gov.br/classif/api/publico/nomenclatura/download/json?perfil=PUBLICO',
    ];

    public function sincronizar(): array
    {
        $custom = trim((string) config('services.ncm_classif.url'));

        if ($custom !== '') {
            array_unshift($this->fontes, $custom);
        }

        $lastError = null;

        foreach (array_unique($this->fontes) as $url) {
            try {
                $response = Http::timeout(90)
                    ->retry(2, 1200)
                    ->acceptJson()
                    ->withHeaders(['User-Agent' => 'IntelbrasTech-ERP/1.0'])
                    ->get($url);

                if (!$response->successful()) {
                    $lastError = "HTTP {$response->status()} em {$url}";
                    continue;
                }

                $payload = $response->json();

                if (!is_array($payload)) {
                    $lastError = "Resposta inválida em {$url}";
                    continue;
                }

                $items = $this->extrairItens($payload);

                if ($items->isEmpty()) {
                    $lastError = "Nenhum código NCM reconhecido em {$url}";
                    continue;
                }

                $now = now();
                $total = 0;

                foreach ($items->chunk(500) as $chunk) {
                    $rows = [];

                    foreach ($chunk as $item) {
                        $rows[] = [
                            'codigo' => $item['codigo'],
                            'descricao' => $item['descricao'],
                            'nivel' => $item['nivel'],
                            'codigo_pai' => $item['codigo_pai'],
                            'vigencia_inicio' => $item['vigencia_inicio'],
                            'vigencia_fim' => $item['vigencia_fim'],
                            'ativo' => $item['ativo'],
                            'fonte' => 'classif_receita_federal',
                            'dados' => json_encode($item['dados'], JSON_UNESCAPED_UNICODE),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    DB::table('ncm_catalogo')->upsert(
                        $rows,
                        ['codigo'],
                        ['descricao', 'nivel', 'codigo_pai', 'vigencia_inicio', 'vigencia_fim', 'ativo', 'fonte', 'dados', 'updated_at']
                    );

                    $total += count($rows);
                }

                return [
                    'total' => $total,
                    'fonte' => $url,
                    'sincronizado_em' => $now->toIso8601String(),
                ];
            } catch (Throwable $e) {
                report($e);
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException(
            'Não foi possível atualizar a tabela oficial NCM. ' .
            ($lastError ? "Detalhe: {$lastError}" : 'Verifique a conexão com o Sistema Classif.')
        );
    }

    public function buscar(string $consulta, ?string $gtin = null, int $limite = 20): array
    {
        $consulta = trim($consulta);
        $limite = max(1, min($limite, 50));

        if ($consulta === '' && !$gtin) {
            return [];
        }

        if (DB::table('ncm_catalogo')->count() === 0) {
            try {
                $this->sincronizar();
            } catch (Throwable $e) {
                // A busca continuará vazia e a API devolverá a orientação de sincronização.
            }
        }

        $codigo = preg_replace('/\D+/', '', $consulta);
        $termos = collect(preg_split('/\s+/u', Str::lower($consulta)))
            ->map(fn ($t) => preg_replace('/[^\pL\pN]+/u', '', $t))
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->unique()
            ->values();

        $query = DB::table('ncm_catalogo')->where('ativo', true);

        $query->where(function ($q) use ($consulta, $codigo, $termos) {
            if (strlen($codigo) >= 4) {
                $q->orWhere('codigo', 'like', $codigo . '%');
            }

            if ($consulta !== '') {
                $q->orWhere('descricao', 'like', '%' . $consulta . '%');
            }

            foreach ($termos as $termo) {
                $q->orWhere('descricao', 'like', '%' . $termo . '%');
            }
        });

        $items = $query
            ->limit(250)
            ->get(['codigo', 'descricao', 'nivel', 'codigo_pai', 'vigencia_inicio', 'vigencia_fim']);

        return $items
            ->map(function ($item) use ($consulta, $codigo, $termos) {
                $descricao = Str::lower($item->descricao);
                $score = 0;

                if ($codigo && str_starts_with($item->codigo, $codigo)) {
                    $score += 100;
                }

                if ($consulta !== '' && Str::contains($descricao, Str::lower($consulta))) {
                    $score += 70;
                }

                foreach ($termos as $termo) {
                    if (Str::contains($descricao, $termo)) {
                        $score += 15;
                    }
                }

                // Códigos completos de 8 dígitos recebem prioridade.
                $digits = strlen(preg_replace('/\D+/', '', $item->codigo));
                if ($digits === 8) {
                    $score += 20;
                }

                return [
                    'codigo' => $item->codigo,
                    'codigo_formatado' => $this->formatar($item->codigo),
                    'descricao' => $item->descricao,
                    'nivel' => $item->nivel,
                    'codigo_pai' => $item->codigo_pai,
                    'vigencia_inicio' => $item->vigencia_inicio,
                    'vigencia_fim' => $item->vigencia_fim,
                    'score' => $score,
                    'confianca' => min(99, max(20, $score)),
                ];
            })
            ->sortByDesc('score')
            ->unique('codigo')
            ->take($limite)
            ->values()
            ->all();
    }

    private function extrairItens(array $payload): Collection
    {
        $candidates = [
            $payload['Nomenclaturas'] ?? null,
            $payload['nomenclaturas'] ?? null,
            $payload['data'] ?? null,
            $payload['items'] ?? null,
            $payload,
        ];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $items = collect($candidate)
                ->map(fn ($row) => $this->normalizarItem($row))
                ->filter()
                ->unique('codigo')
                ->values();

            if ($items->count() > 100) {
                return $items;
            }
        }

        return collect();
    }

    private function normalizarItem(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        $codigo = $row['Codigo'] ?? $row['codigo'] ?? $row['Code'] ?? $row['code'] ?? null;
        $descricao = $row['Descricao'] ?? $row['descricao'] ?? $row['Description'] ?? $row['description'] ?? null;

        if (!$codigo || !$descricao) {
            return null;
        }

        $codigoLimpo = preg_replace('/\D+/', '', (string) $codigo);

        if (strlen($codigoLimpo) < 4 || strlen($codigoLimpo) > 8) {
            return null;
        }

        return [
            'codigo' => $codigoLimpo,
            'descricao' => trim(strip_tags((string) $descricao)),
            'nivel' => $row['Nivel'] ?? $row['nivel'] ?? null,
            'codigo_pai' => preg_replace('/\D+/', '', (string) ($row['CodigoPai'] ?? $row['codigoPai'] ?? $row['codigo_pai'] ?? '')) ?: null,
            'vigencia_inicio' => $this->data($row['DataInicio'] ?? $row['dataInicio'] ?? $row['vigencia_inicio'] ?? null),
            'vigencia_fim' => $this->data($row['DataFim'] ?? $row['dataFim'] ?? $row['vigencia_fim'] ?? null),
            'ativo' => !isset($row['Ativo']) || (bool) $row['Ativo'],
            'dados' => $row,
        ];
    }

    private function data(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return now()->parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function formatar(string $codigo): string
    {
        $codigo = preg_replace('/\D+/', '', $codigo);

        return strlen($codigo) === 8
            ? substr($codigo, 0, 4) . '.' . substr($codigo, 4, 2) . '.' . substr($codigo, 6, 2)
            : $codigo;
    }
}
