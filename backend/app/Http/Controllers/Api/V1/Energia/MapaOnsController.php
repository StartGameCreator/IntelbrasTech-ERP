<?php

namespace App\Http\Controllers\Api\V1\Energia;

use App\Http\Controllers\Controller;
use App\Models\{OnsUsina, Lead, Pipeline, PipelineEtapa};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MapaOnsController extends Controller
{
    private const ANEEL_CSV = 'https://dadosabertos.aneel.gov.br/dataset/6d90b77c-c5f5-4d81-bdec-7bc619494bb9/resource/11ec447d-698d-4ab8-977f-b424d5deee6a/download/siga-empreendimentos-geracao.csv';

    public function index(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        $query = OnsUsina::query()
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = trim((string) $request->busca);
                $q->where(function ($qq) use ($busca) {
                    $qq->where('nome', 'like', "%{$busca}%")
                       ->orWhere('codigo_ceg', 'like', "%{$busca}%")
                       ->orWhere('municipio', 'like', "%{$busca}%");
                });
            })
            ->when($request->filled('uf'), fn ($q) => $q->where('uf', strtoupper($request->uf)))
            ->when($request->filled('fase'), fn ($q) => $q->where('fase', $request->fase))
            ->when($request->filled('potencia_min'), fn ($q) => $q->where('potencia_mw', '>=', (float) $request->potencia_min))
            ->when($request->filled('potencia_max'), fn ($q) => $q->where('potencia_mw', '<=', (float) $request->potencia_max))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('potencia_mw');

        $limit = min(max((int) $request->get('limit', 1000), 1), 5000);
        $items = $query->limit($limit)->get()->map(function ($usina) {
            $cnpjs = $this->extrairCnpjs($usina->proprietarios);
            $usina->setAttribute('cnpjs_proprietarios', $cnpjs);
            $usina->setAttribute('cnpj_principal', $cnpjs[0] ?? null);
            return $usina;
        });

        return response()->json([
            'data' => $items,
            'total' => $items->count(),
            'atualizado_em' => OnsUsina::max('fonte_atualizada_em'),
            'fonte' => 'ANEEL SIGA / referência operacional ONS',
        ]);
    }

    public function resumo(): JsonResponse
    {
        $this->authorize('crm.view');

        return response()->json([
            'total' => OnsUsina::count(),
            'operacao' => OnsUsina::where('fase', 'Operação')->count(),
            'construcao' => OnsUsina::where('fase', 'Construção')->count(),
            'potencia_mw' => round((float) OnsUsina::sum('potencia_mw'), 2),
            'ufs' => OnsUsina::select('uf')->distinct()->whereNotNull('uf')->orderBy('uf')->pluck('uf'),
            'fases' => OnsUsina::select('fase')->distinct()->whereNotNull('fase')->orderBy('fase')->pluck('fase'),
        ]);
    }

    public function sincronizar(): JsonResponse
    {
        $this->authorize('crm.edit');

        $response = Http::timeout(120)->retry(2, 1500)->get(self::ANEEL_CSV);
        if (! $response->successful()) {
            return response()->json(['message' => 'Não foi possível baixar a base pública da ANEEL.'], 502);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'siga_');
        file_put_contents($tmp, $response->body());
        $handle = fopen($tmp, 'r');
        if (! $handle) {
            return response()->json(['message' => 'Não foi possível processar o arquivo recebido.'], 500);
        }

        $headerLine = fgets($handle);
        $headerLine = mb_convert_encoding($headerLine, 'UTF-8', 'Windows-1252');
        $headers = str_getcsv(trim($headerLine), ';');
        $headers = array_map(fn ($v) => trim($v, "\" \t\n\r\0\x0B"), $headers);

        $batch = [];
        $count = 0;
        $agora = now();

        DB::beginTransaction();
        try {
            while (($line = fgets($handle)) !== false) {
                $line = mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
                $values = str_getcsv(trim($line), ';');
                if (count($values) !== count($headers)) {
                    continue;
                }

                $row = array_combine($headers, $values);
                if (($row['SigTipoGeracao'] ?? null) !== 'UFV') {
                    continue;
                }

                $lat = $this->numero($row['NumCoordNEmpreendimento'] ?? null);
                $lng = $this->numero($row['NumCoordEEmpreendimento'] ?? null);
                $potenciaKw = $this->numero($row['MdaPotenciaFiscalizadaKw'] ?? null)
                    ?: $this->numero($row['MdaPotenciaOutorgadaKw'] ?? null);

                $municipio = trim((string) ($row['DscMuninicpios'] ?? ''));
                $batch[] = [
                    'codigo_ceg' => trim((string) ($row['CodCEG'] ?? '')),
                    'nome' => trim((string) ($row['NomEmpreendimento'] ?? '')),
                    'uf' => trim((string) ($row['SigUFPrincipal'] ?? '')),
                    'municipio' => $municipio,
                    'fase' => trim((string) ($row['DscFaseUsina'] ?? '')),
                    'fonte' => trim((string) ($row['NomFonteCombustivel'] ?? 'Solar Fotovoltaica')),
                    'potencia_mw' => $potenciaKw ? round($potenciaKw / 1000, 6) : null,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'entrada_operacao' => $this->data($row['DatEntradaOperacao'] ?? null),
                    'proprietarios' => trim((string) ($row['DscPropriRegimePariticipacao'] ?? '')),
                    'sub_bacia' => trim((string) ($row['DscSubBacia'] ?? '')),
                    'fonte_dados' => 'ANEEL SIGA',
                    'fonte_atualizada_em' => $agora,
                    'updated_at' => $agora,
                    'created_at' => $agora,
                ];

                if (count($batch) >= 500) {
                    $this->gravar($batch);
                    $count += count($batch);
                    $batch = [];
                }
            }

            if ($batch) {
                $this->gravar($batch);
                $count += count($batch);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            @unlink($tmp);
            report($e);
            return response()->json(['message' => 'Falha ao sincronizar a base.', 'erro' => $e->getMessage()], 500);
        }

        fclose($handle);
        @unlink($tmp);

        return response()->json([
            'message' => 'Base de usinas fotovoltaicas sincronizada.',
            'processadas' => $count,
            'total' => OnsUsina::count(),
        ]);
    }

    public function importarLead(Request $request, int $id): JsonResponse
    {
        $this->authorize('crm.create');

        $usina = OnsUsina::findOrFail($id);

        // INTELBRASTECH_CNPJ_TRANSPORTE_V4
        $cnpjsDetectados = $this->extrairCnpjs($usina->proprietarios);

        $cnpjRecebido = preg_replace(
            '/\D/',
            '',
            (string) $request->input('cnpj', '')
        );

        $cnpjPrincipal = strlen($cnpjRecebido) === 14
            ? $cnpjRecebido
            : ($cnpjsDetectados[0] ?? null);
        $cnpjs = $this->extrairCnpjs($usina->proprietarios);
        $cnpjPrincipal = preg_replace('/\D/', '', (string) $request->input('cnpj', $cnpjs[0] ?? ''));
        $cnpjPrincipal = strlen($cnpjPrincipal) === 14 ? $cnpjPrincipal : null;
        $empresaId = auth()->user()->empresa_id;

        $duplicado = Lead::where('empresa_id', $empresaId)
            ->where('origem', 'mapa_ons')
            ->where('tags', 'like', '%CEG:' . $usina->codigo_ceg . '%')
            ->first();

        if ($duplicado) {
            return response()->json(['message' => 'Esta usina já está no CRM.', 'lead' => $duplicado], 409);
        }

        $pipeline = Pipeline::where('empresa_id', $empresaId)->where('ativo', true)->first();
        $etapaId = $pipeline
            ? PipelineEtapa::where('pipeline_id', $pipeline->id)->orderBy('ordem')->value('id')
            : null;

        $lead = Lead::create([
            'empresa_id' => $empresaId,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'pipeline_id' => $pipeline?->id,
            'etapa_id' => $etapaId,
            'responsavel_id' => auth()->id(),
            'nome' => $usina->nome,
            'empresa_nome' => $usina->proprietarios ?: $usina->nome,
            'cnpj' => $cnpjPrincipal,
            'cidade' => $usina->municipio,
            'uf' => $usina->uf,
            'latitude' => $usina->latitude,
            'longitude' => $usina->longitude,
            'fonte_referencia' => 'ANEEL SIGA / Mapa ONS',
            'dados_extras' => json_encode([
                'codigo_ceg' => $usina->codigo_ceg,
                'potencia_mw' => $usina->potencia_mw,
                'fase' => $usina->fase,
                'proprietarios' => $usina->proprietarios,
                'cnpjs_proprietarios' => $cnpjs,
                'status_enriquecimento' => 'pendente',
                'cnpj_principal' => $cnpjPrincipal,
                'cnpjs_detectados' => $cnpjsDetectados,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'titulo' => 'Oportunidade comercial - Usina Fotovoltaica',
            'origem' => 'mapa_ons',
            'valor_estimado' => 0,
            'probabilidade' => 10,
            'status' => Lead::STATUS_ABERTO,
            'tags' => implode(',', array_filter([
                'Mapa ONS',
                'UFV',
                'CEG:' . $usina->codigo_ceg,
                $cnpjPrincipal ? 'CNPJ:' . $cnpjPrincipal : null,
                'Aguardando enriquecimento',
                $usina->uf,
                $usina->potencia_mw ? $usina->potencia_mw . 'MW' : null,
            ])),
        ]);

        $usina->update(['lead_id' => $lead->id, 'importada_crm_em' => now()]);

        return response()->json([
            'message' => 'Usina adicionada ao CRM para prospecção.',
            'lead' => $lead->fresh(['etapa', 'responsavel']),
            'cnpj_salvo' => $lead->cnpj,
        ], 201);
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
    private function gravar(array $batch): void
    {
        OnsUsina::upsert(
            $batch,
            ['codigo_ceg'],
            ['nome','uf','municipio','fase','fonte','potencia_mw','latitude','longitude','entrada_operacao','proprietarios','sub_bacia','fonte_dados','fonte_atualizada_em','updated_at']
        );
    }

    private function numero(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $value = str_replace(['.', ','], ['', '.'], $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function data(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {}
        }
        return null;
    }
}
