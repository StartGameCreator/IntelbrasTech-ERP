<?php

namespace App\Http\Controllers\Api\V1\Tecnicos;

use App\Http\Controllers\Controller;
use App\Models\OrdemServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperacaoTecnicaController extends Controller
{
    public function painel(Request $request): JsonResponse
    {
        $tecnico = $this->tecnicoAtual($request);
        $oportunidades = $this->oportunidades($tecnico);
        $atribuicoes = $this->atribuicoes($tecnico);

        return response()->json([
            'tecnico' => $this->formatarTecnico($tecnico),
            'oportunidades' => $oportunidades->values(),
            'atribuicoes' => $atribuicoes->values(),
            'resumo' => [
                'oportunidades_proximas' => $oportunidades->filter(fn (array $demanda) => $demanda['dentro_do_raio'])->count(),
                'oportunidades_total' => $oportunidades->count(),
                'atendimentos_em_andamento' => $atribuicoes->count(),
            ],
        ]);
    }

    public function atualizarDisponibilidade(Request $request): JsonResponse
    {
        $dados = $request->validate(['disponivel' => ['required', 'boolean']]);
        $tecnico = $this->tecnicoAtual($request);

        DB::table('platform_cadastros')->where('id', $tecnico->id)->update([
            'disponivel_para_atendimento' => (bool) $dados['disponivel'],
            'disponibilidade_atualizada_em' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => $dados['disponivel']
                ? 'Você está disponível para novos atendimentos.'
                : 'Sua disponibilidade para novos atendimentos foi pausada.',
        ]);
    }

    public function atualizarLocalizacao(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $tecnico = $this->tecnicoAtual($request);

        DB::table('platform_cadastros')->where('id', $tecnico->id)->update([
            'latitude' => $dados['latitude'],
            'longitude' => $dados['longitude'],
            'localizacao_tipo' => 'exata_gps',
            'localizacao_compartilhada' => true,
            'localizacao_atualizada_em' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Localização atualizada para ordenar as oportunidades próximas.']);
    }

    public function aceitar(Request $request, string $origem, int $id): JsonResponse
    {
        $origem = strtolower($origem);
        abort_unless(in_array($origem, ['os', 'visita'], true), 404);

        $tecnico = $this->tecnicoAtual($request);

        if (! (bool) $tecnico->disponivel_para_atendimento) {
            throw ValidationException::withMessages([
                'disponivel' => ['Ative sua disponibilidade antes de aceitar uma oportunidade.'],
            ]);
        }

        DB::transaction(function () use ($origem, $id, $tecnico): void {
            if ($origem === 'os') {
                $ordem = OrdemServico::query()
                    ->where('empresa_id', $tecnico->empresa_id)
                    ->lockForUpdate()
                    ->findOrFail($id);

                if ($ordem->data_conclusao || $ordem->tecnico_id) {
                    throw ValidationException::withMessages([
                        'oportunidade' => ['Esta ordem de serviço já foi assumida por outro técnico ou finalizada.'],
                    ]);
                }

                $ordem->update(['tecnico_id' => $tecnico->usuario_id]);

                return;
            }

            $visita = DB::table('platform_cadastros')
                ->where('empresa_id', $tecnico->empresa_id)
                ->where('tipo_perfil', 'cliente')
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->where('id', $id)
                ->first();

            abort_unless($visita, 404, 'Solicitação não encontrada.');
            $metadados = $this->json($visita->metadados ?? null);
            $solicitacao = (array) ($metadados['solicitacao_visita'] ?? []);
            $gestao = (array) ($metadados['gestao_visita'] ?? []);

            if (! $solicitacao || filled($gestao['responsavel_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'oportunidade' => ['Esta solicitação já foi assumida por outro técnico.'],
                ]);
            }

            $metadados['gestao_visita'] = [
                'responsavel_id' => (int) $tecnico->usuario_id,
                'responsavel_nome' => $tecnico->nome,
                'atribuido_em' => now()->toIso8601String(),
                'status' => 'atribuida',
            ];

            DB::table('platform_cadastros')->where('id', $visita->id)->update([
                'metadados' => json_encode($metadados, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Atendimento assumido. Ele já aparece na sua agenda operacional.']);
    }

    private function tecnicoAtual(Request $request): object
    {
        $tecnico = DB::table('platform_cadastros as cadastro')
            ->join('usuarios as usuario', 'usuario.id', '=', 'cadastro.usuario_id')
            ->where('cadastro.usuario_id', $request->user()->id)
            ->where('cadastro.tipo_perfil', 'tecnico')
            ->where('cadastro.status', 'aprovado')
            ->where('usuario.ativo', true)
            ->whereNull('cadastro.deleted_at')
            ->select([
                'cadastro.id', 'cadastro.usuario_id', 'cadastro.empresa_id', 'cadastro.nome',
                'cadastro.cidade', 'cadastro.estado', 'cadastro.latitude', 'cadastro.longitude',
                'cadastro.localizacao_tipo', 'cadastro.localizacao_atualizada_em',
                'cadastro.disponivel_para_atendimento', 'cadastro.disponibilidade_atualizada_em',
                'cadastro.raio_atendimento_km', 'cadastro.especialidades',
            ])
            ->first();

        abort_unless($tecnico, 403, 'Este acesso é exclusivo para técnicos aprovados.');

        return $tecnico;
    }

    private function formatarTecnico(object $tecnico): array
    {
        $latitude = $this->numero($tecnico->latitude ?? null);
        $longitude = $this->numero($tecnico->longitude ?? null);

        return [
            'id' => (int) $tecnico->id,
            'nome' => $tecnico->nome,
            'disponivel' => (bool) $tecnico->disponivel_para_atendimento,
            'disponibilidade_atualizada_em' => $tecnico->disponibilidade_atualizada_em,
            'raio_atendimento_km' => (int) ($tecnico->raio_atendimento_km ?: 50),
            'especialidades' => $tecnico->especialidades,
            'localizacao' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'precisa_atualizar' => ! $this->coordenadasValidas($latitude, $longitude),
                'atualizada_em' => $tecnico->localizacao_atualizada_em,
                'tipo' => $tecnico->localizacao_tipo,
            ],
        ];
    }

    private function oportunidades(object $tecnico): Collection
    {
        $demandas = collect();

        $ordens = OrdemServico::query()
            ->with(['cliente:id,razao_social,nome_fantasia,logradouro,numero,bairro,cidade,estado,latitude,longitude', 'status:id,nome'])
            ->where('empresa_id', $tecnico->empresa_id)
            ->whereNull('data_conclusao')
            ->whereNull('tecnico_id')
            ->latest('updated_at')
            ->limit(150)
            ->get()
            ->map(function (OrdemServico $ordem) use ($tecnico) {
                $cliente = $ordem->cliente;

                return $this->demandaComLocalizacao([
                    'id' => $ordem->id,
                    'origem' => 'os',
                    'titulo' => 'OS #' . $ordem->numero . ' — ' . ($ordem->equipamento_modelo ?: $ordem->equipamento_tipo ?: 'Atendimento técnico'),
                    'cliente' => $cliente?->razao_social ?: $cliente?->nome_fantasia ?: 'Cliente não identificado',
                    'descricao' => $ordem->defeito_relatado ?: $ordem->observacoes,
                    'status' => $ordem->status?->nome ?: 'Em aberto',
                    'prioridade' => $ordem->data_previsao && $ordem->data_previsao->isPast() ? 'alta' : 'normal',
                    'criada_em' => optional($ordem->created_at)->toIso8601String(),
                    'url' => '/os/' . $ordem->id,
                    'logradouro' => $cliente?->logradouro,
                    'numero' => $cliente?->numero,
                    'bairro' => $cliente?->bairro,
                    'cidade' => $cliente?->cidade,
                    'estado' => $cliente?->estado,
                    'latitude' => $cliente?->latitude,
                    'longitude' => $cliente?->longitude,
                ], $tecnico);
            });

        $visitas = DB::table('platform_cadastros')
            ->where('empresa_id', $tecnico->empresa_id)
            ->where('tipo_perfil', 'cliente')
            ->whereNull('deleted_at')
            ->latest('updated_at')
            ->limit(150)
            ->get(['id', 'nome', 'empresa', 'logradouro', 'numero', 'bairro', 'cidade', 'estado', 'latitude', 'longitude', 'metadados', 'created_at'])
            ->map(function (object $visita) use ($tecnico) {
                $metadados = $this->json($visita->metadados ?? null);
                $solicitacao = (array) ($metadados['solicitacao_visita'] ?? []);
                $gestao = (array) ($metadados['gestao_visita'] ?? []);

                if (! $solicitacao || filled($gestao['responsavel_id'] ?? null)) {
                    return null;
                }

                return $this->demandaComLocalizacao([
                    'id' => $visita->id,
                    'origem' => 'visita',
                    'titulo' => $solicitacao['servico'] ?? 'Solicitação de visita técnica',
                    'cliente' => $visita->empresa ?: $visita->nome,
                    'descricao' => $solicitacao['descricao'] ?? $solicitacao['observacoes'] ?? null,
                    'status' => $solicitacao['status'] ?? 'Solicitada',
                    'prioridade' => 'normal',
                    'criada_em' => $solicitacao['solicitada_em'] ?? $visita->created_at,
                    'url' => null,
                    'logradouro' => $visita->logradouro,
                    'numero' => $visita->numero,
                    'bairro' => $visita->bairro,
                    'cidade' => $visita->cidade,
                    'estado' => $visita->estado,
                    'latitude' => $visita->latitude,
                    'longitude' => $visita->longitude,
                ], $tecnico);
            })
            ->filter();

        return $demandas
            ->concat($ordens)
            ->concat($visitas)
            ->sortBy(fn (array $demanda) => $demanda['distancia_km'] ?? PHP_FLOAT_MAX)
            ->values();
    }

    private function atribuicoes(object $tecnico): Collection
    {
        $ordens = OrdemServico::query()
            ->with(['cliente:id,razao_social,nome_fantasia,logradouro,numero,bairro,cidade,estado,latitude,longitude', 'status:id,nome'])
            ->where('empresa_id', $tecnico->empresa_id)
            ->whereNull('data_conclusao')
            ->where('tecnico_id', $tecnico->usuario_id)
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(function (OrdemServico $ordem) use ($tecnico) {
                $cliente = $ordem->cliente;

                return $this->demandaComLocalizacao([
                    'id' => $ordem->id,
                    'origem' => 'os',
                    'titulo' => 'OS #' . $ordem->numero . ' — ' . ($ordem->equipamento_modelo ?: $ordem->equipamento_tipo ?: 'Atendimento técnico'),
                    'cliente' => $cliente?->razao_social ?: $cliente?->nome_fantasia ?: 'Cliente não identificado',
                    'descricao' => $ordem->defeito_relatado ?: $ordem->observacoes,
                    'status' => $ordem->status?->nome ?: 'Em aberto',
                    'prioridade' => $ordem->data_previsao && $ordem->data_previsao->isPast() ? 'alta' : 'normal',
                    'criada_em' => optional($ordem->created_at)->toIso8601String(),
                    'url' => '/os/' . $ordem->id,
                    'logradouro' => $cliente?->logradouro,
                    'numero' => $cliente?->numero,
                    'bairro' => $cliente?->bairro,
                    'cidade' => $cliente?->cidade,
                    'estado' => $cliente?->estado,
                    'latitude' => $cliente?->latitude,
                    'longitude' => $cliente?->longitude,
                ], $tecnico);
            });

        $visitas = DB::table('platform_cadastros')
            ->where('empresa_id', $tecnico->empresa_id)
            ->where('tipo_perfil', 'cliente')
            ->whereNull('deleted_at')
            ->latest('updated_at')
            ->limit(150)
            ->get(['id', 'nome', 'empresa', 'logradouro', 'numero', 'bairro', 'cidade', 'estado', 'latitude', 'longitude', 'metadados', 'created_at'])
            ->map(function (object $visita) use ($tecnico) {
                $metadados = $this->json($visita->metadados ?? null);
                $solicitacao = (array) ($metadados['solicitacao_visita'] ?? []);
                $gestao = (array) ($metadados['gestao_visita'] ?? []);

                if (($gestao['responsavel_id'] ?? null) != $tecnico->usuario_id) {
                    return null;
                }

                return $this->demandaComLocalizacao([
                    'id' => $visita->id,
                    'origem' => 'visita',
                    'titulo' => $solicitacao['servico'] ?? 'Visita técnica',
                    'cliente' => $visita->empresa ?: $visita->nome,
                    'descricao' => $solicitacao['descricao'] ?? $solicitacao['observacoes'] ?? null,
                    'status' => $gestao['status'] ?? 'Atribuída',
                    'prioridade' => 'normal',
                    'criada_em' => $solicitacao['solicitada_em'] ?? $visita->created_at,
                    'url' => null,
                    'logradouro' => $visita->logradouro,
                    'numero' => $visita->numero,
                    'bairro' => $visita->bairro,
                    'cidade' => $visita->cidade,
                    'estado' => $visita->estado,
                    'latitude' => $visita->latitude,
                    'longitude' => $visita->longitude,
                ], $tecnico);
            })
            ->filter();

        return $ordens->concat($visitas)->sortBy(fn (array $demanda) => $demanda['distancia_km'] ?? PHP_FLOAT_MAX)->values();
    }

    private function demandaComLocalizacao(array $demanda, object $tecnico): array
    {
        $latitude = $this->numero($demanda['latitude'] ?? null);
        $longitude = $this->numero($demanda['longitude'] ?? null);
        $aproximada = ! $this->coordenadasValidas($latitude, $longitude);

        if ($aproximada) {
            [$latitude, $longitude] = $this->coordenadaAproximada(
                (string) ($demanda['estado'] ?? ''),
                (string) ($demanda['cidade'] ?? ''),
                (int) $demanda['id']
            );
        }

        $tecnicoLat = $this->numero($tecnico->latitude ?? null);
        $tecnicoLng = $this->numero($tecnico->longitude ?? null);
        $distancia = $this->coordenadasValidas($tecnicoLat, $tecnicoLng)
            ? round($this->distanciaKm($tecnicoLat, $tecnicoLng, $latitude, $longitude), 1)
            : null;
        $raio = (int) ($tecnico->raio_atendimento_km ?: 50);

        unset($demanda['latitude'], $demanda['longitude']);

        return array_merge($demanda, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'localizacao_aproximada' => $aproximada,
            'distancia_km' => $distancia,
            'dentro_do_raio' => $distancia !== null && $distancia <= $raio,
            'endereco' => trim(implode(', ', array_filter([
                trim((string) ($demanda['logradouro'] ?? '')) . (filled($demanda['numero'] ?? null) ? ' ' . $demanda['numero'] : ''),
                $demanda['bairro'] ?? null,
                trim((string) ($demanda['cidade'] ?? '')) . (filled($demanda['estado'] ?? null) ? '/' . strtoupper((string) $demanda['estado']) : ''),
            ]))),
        ]);
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function numero(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numero = (float) str_replace(',', '.', (string) $value);

        return is_finite($numero) ? $numero : null;
    }

    private function coordenadasValidas(?float $latitude, ?float $longitude): bool
    {
        return $latitude !== null && $longitude !== null
            && $latitude >= -34.5 && $latitude <= 6
            && $longitude >= -74 && $longitude <= -30;
    }

    private function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $raioTerra = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $raioTerra * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function coordenadaAproximada(string $uf, string $cidade, int $id): array
    {
        $centros = [
            'AC' => [-9.02, -70.81], 'AL' => [-9.62, -36.82], 'AP' => [0.90, -52.00],
            'AM' => [-3.47, -65.10], 'BA' => [-12.97, -41.70], 'CE' => [-5.20, -39.53],
            'DF' => [-15.78, -47.93], 'ES' => [-19.19, -40.34], 'GO' => [-16.64, -49.31],
            'MA' => [-5.42, -45.44], 'MT' => [-12.64, -55.42], 'MS' => [-20.51, -54.54],
            'MG' => [-18.10, -44.38], 'PA' => [-3.79, -52.48], 'PB' => [-7.12, -36.72],
            'PR' => [-24.89, -51.55], 'PE' => [-8.38, -37.86], 'PI' => [-7.71, -42.73],
            'RJ' => [-22.25, -42.66], 'RN' => [-5.81, -36.59], 'RS' => [-30.03, -53.78],
            'RO' => [-10.83, -63.34], 'RR' => [2.05, -61.39], 'SC' => [-27.33, -49.44],
            'SP' => [-22.19, -48.79], 'SE' => [-10.57, -37.45], 'TO' => [-10.25, -48.25],
        ];

        $base = $centros[strtoupper(trim($uf))] ?? [-15.78, -47.93];
        $hash = abs(crc32(mb_strtoupper($cidade ?: (string) $id)));

        return [
            round($base[0] + ((($hash % 1000) / 1000 - 0.5) * 1.4), 6),
            round($base[1] + ((intdiv($hash, 1000) % 1000) / 1000 - 0.5) * 1.4, 6),
        ];
    }
}
