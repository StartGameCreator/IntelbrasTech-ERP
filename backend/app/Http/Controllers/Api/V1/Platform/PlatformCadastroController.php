<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PlatformCadastroController extends Controller
{
    public function categorias(): JsonResponse
    {
        $nomes = [
            'Técnico de Informática', 'Técnico de TV', 'Técnico Eletrotécnico',
            'Automação Residencial', 'Automação Industrial', 'Bombeiro Hidráulico',
            'Mestre de Obras', 'Pedreiro', 'Ladrilheiro', 'Segurança Eletrônica',
            'Eletricista', 'Energia Solar', 'Vendedor Técnico',
            'Técnico de Portões', 'Técnico de Impressoras',
        ];

        foreach ($nomes as $ordem => $nome) {
            DB::table('platform_categorias_tecnicas')->updateOrInsert(
                ['slug' => Str::slug($nome)],
                [
                    'nome' => $nome,
                    'ativo' => true,
                    'ordem' => $ordem + 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(
            DB::table('platform_categorias_tecnicas')
                ->where('ativo', true)
                ->orderBy('ordem')
                ->get(['id', 'nome', 'slug', 'descricao'])
        );
    }

    public function cadastrar(Request $request, string $tipo): JsonResponse
    {
        // Perfis disponíveis nas duas jornadas públicas: parceiros e solicitação técnica.
        abort_unless(in_array($tipo, ['tecnico', 'vendedor', 'cliente'], true), 404);

        $rules = [
            'nome' => 'required|string|max:180',
            'nome_exibicao' => 'nullable|string|max:180',
            'email' => [
                'required', 'email', 'max:180',
                Rule::unique('platform_cadastros', 'email')
                    ->where(fn ($q) => $q->where('tipo_perfil', $tipo)->whereNull('deleted_at')),
            ],
            'senha' => 'required_without:social_provider|nullable|string|min:8|confirmed',
            'social_provider' => ['nullable', Rule::in(['google', 'microsoft', 'apple', 'facebook'])],
            'telefone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'tipo_pessoa' => ['nullable', Rule::in(['PF', 'PJ'])],
            'modalidade_cliente' => ['nullable', Rule::in(['contratante_cpf', 'contratante_cnpj', 'condominio'])],
            'cpf' => 'nullable|string|max:20',
            'rg' => 'nullable|string|max:30',
            'cnpj' => 'nullable|string|max:20',
            'empresa' => 'nullable|string|max:180',
            'cargo' => 'nullable|string|max:120',
            'cep' => 'nullable|string|max:12',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:30',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:150',
            'cidade' => 'nullable|string|max:150',
            'estado' => 'nullable|string|size:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'localizacao_tipo' => ['nullable', Rule::in(['aproximada_cep', 'exata_gps'])],
            'localizacao_compartilhada' => 'nullable|boolean',
            'data_nascimento' => 'nullable|date',
            'motivacao' => 'nullable|string|max:5000',
            'metadados' => 'nullable|array',
            'metadados.solicitacao_visita' => 'nullable|array',
            'metadados.solicitacao_visita.categoria_id' => 'nullable|integer|exists:platform_categorias_tecnicas,id',
            'metadados.solicitacao_visita.servico' => 'nullable|string|max:180',
            'metadados.solicitacao_visita.descricao' => 'nullable|string|max:5000',
            'metadados.solicitacao_visita.data_preferencial' => 'nullable|date|after_or_equal:today',
            'metadados.solicitacao_visita.observacoes' => 'nullable|string|max:2000',
            'metadados.solicitacao_visita.status' => ['nullable', Rule::in(['solicitada'])],
            'metadados.solicitacao_visita.solicitada_em' => 'nullable|date',
        ];

        if ($tipo === 'tecnico') {
            $rules += [
                'crea' => 'nullable|string|max:50',
                'crt' => 'nullable|string|max:50',
                'cft' => 'nullable|string|max:50',
                'anos_experiencia' => 'nullable|integer|min:0|max:80',
                'raio_atendimento_km' => 'nullable|integer|min:1|max:1000',
                'especialidades' => 'required|string|max:5000',
                'experiencia' => 'nullable|string|max:10000',
                'categorias' => 'required|array|min:1',
                'categorias.*' => 'integer|exists:platform_categorias_tecnicas,id',
                'certificados' => 'nullable|array|max:12',
                'certificados.*.nome' => 'nullable|string|max:180',
                'certificados.*.url' => 'nullable|url|max:500',
                'portfolio' => 'nullable|array|max:12',
                'portfolio.*.titulo' => 'nullable|string|max:180',
                'portfolio.*.descricao' => 'nullable|string|max:500',
                'portfolio.*.url' => 'nullable|url|max:500',
                'metadados.foto_url' => 'nullable|url|max:500',
            ];
        }

        if ($tipo === 'colaborador') {
            $rules += [
                'nivel_colaborador' => [
                    'required',
                    Rule::in(['administrador_jr', 'administrador_pleno', 'administrador_senior']),
                ],
                'cargo' => 'required|string|max:120',
                'motivacao' => 'required|string|min:30|max:5000',
            ];
        }

        if ($tipo === 'vendedor') {
            $rules += [
                'cargo' => 'required|string|max:120',
                'experiencia' => 'required|string|min:20|max:10000',
            ];
        }

        if ($tipo === 'cliente') {
            $rules += [
                'metadados.solicitacao_visita' => 'required|array',
                'metadados.solicitacao_visita.categoria_id' => 'required|integer|exists:platform_categorias_tecnicas,id',
                'metadados.solicitacao_visita.descricao' => 'required|string|min:10|max:5000',
            ];
        }

        $data = $request->validate($rules);

        foreach (['cpf', 'cnpj', 'cep', 'telefone', 'whatsapp'] as $campo) {
            if (array_key_exists($campo, $data)) {
                $data[$campo] = preg_replace('/\D+/', '', (string) $data[$campo]) ?: null;
            }
        }

        $senha = $data['senha'] ?? null;
        unset($data['senha'], $data['senha_confirmation']);

        if (($data['localizacao_compartilhada'] ?? false) || (($data['latitude'] ?? null) !== null && ($data['longitude'] ?? null) !== null)) {
            $data['localizacao_atualizada_em'] = now();
            $data['localizacao_compartilhada'] = (bool) ($data['localizacao_compartilhada'] ?? true);
        }

        return DB::transaction(function () use ($data, $senha, $tipo): JsonResponse {
            $empresaId = $this->empresaPrincipalId();
            // Vendedores parceiros só entram na operação comercial após o aceite da equipe.
            // Técnicos mantêm o cadastro aprovado, mas começam indisponíveis até ativarem
            // conscientemente a operação de campo no aplicativo técnico.
            $ativo = ! in_array($tipo, ['colaborador', 'vendedor'], true);
            $status = $ativo ? 'aprovado' : 'pendente';

            $usuarioId = $this->salvarUsuario(
                empresaId: $empresaId,
                tipo: $tipo,
                nome: $data['nome'],
                email: $data['email'],
                telefone: $data['telefone'] ?? null,
                cargo: $data['cargo'] ?? null,
                senha: $senha,
                socialProvider: $data['social_provider'] ?? null,
                ativo: $ativo,
            );

            $cadastro = $data;
            foreach (['categorias', 'certificados', 'portfolio', 'metadados'] as $campoJson) {
                if (isset($cadastro[$campoJson])) {
                    $cadastro[$campoJson] = json_encode($cadastro[$campoJson], JSON_UNESCAPED_UNICODE);
                }
            }

            $cadastro['usuario_id'] = $usuarioId;
            $cadastro['empresa_id'] = $empresaId;
            $cadastro['senha_hash'] = $senha ? Hash::make($senha) : null;

            $id = DB::table('platform_cadastros')->insertGetId(array_merge(
                $this->filtrarColunas('platform_cadastros', $cadastro),
                [
                    'uuid' => (string) Str::uuid(),
                    'tipo_perfil' => $tipo,
                    'status' => $status,
                    'tipo_pessoa' => $data['tipo_pessoa'] ?? 'PF',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ));

            DB::table('platform_perfis_usuario')->updateOrInsert(
                ['usuario_id' => $usuarioId, 'tipo_perfil' => $tipo],
                [
                    'cadastro_id' => $id,
                    'status' => $ativo ? 'ativo' : 'pendente',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'message' => $ativo
                    ? 'Cadastro concluído. Sua conta já pode ser usada no login.'
                    : 'Solicitação recebida. O acesso será liberado após aprovação.',
                'cadastro_id' => $id,
                'usuario_id' => $usuarioId,
                'status' => $status,
                'login_disponivel' => $ativo,
            ], 201);
        });
    }

    public function painel(Request $request): JsonResponse
    {
        $lixeira = $request->boolean('lixeira');
        $query = DB::table('platform_cadastros');

        $lixeira
            ? $query->whereNotNull('deleted_at')
            : $query->whereNull('deleted_at');

        if ($request->filled('tipo')) {
            $query->where('tipo_perfil', $request->string('tipo'));
        }

        if ($request->filled('status') && !$lixeira) {
            $query->where('status', $request->string('status'));
        }

        $base = DB::table('platform_cadastros');

        return response()->json([
            'resumo' => [
                'total' => (clone $base)->whereNull('deleted_at')->count(),
                'tecnicos' => (clone $base)->whereNull('deleted_at')->where('tipo_perfil', 'tecnico')->count(),
                'vendedores' => (clone $base)->whereNull('deleted_at')->where('tipo_perfil', 'vendedor')->count(),
                'clientes' => (clone $base)->whereNull('deleted_at')->where('tipo_perfil', 'cliente')->count(),
                'colaboradores' => (clone $base)->whereNull('deleted_at')->where('tipo_perfil', 'colaborador')->count(),
                'pendentes' => (clone $base)->whereNull('deleted_at')->where('status', 'pendente')->count(),
                'suspensos' => (clone $base)->whereNull('deleted_at')->where('status', 'suspenso')->count(),
                'excluidos' => (clone $base)->whereNotNull('deleted_at')->count(),
            ],
            'cadastros' => $query->orderByDesc('id')->limit(300)->get(),
            'lixeira' => $lixeira,
        ]);
    }

    public function mapaTecnicos(Request $request): JsonResponse
    {
        $query = DB::table('platform_cadastros')
            ->whereNull('deleted_at')
            ->where('tipo_perfil', 'tecnico');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', strtoupper((string) $request->estado));
        }

        $latitudeColumn = Schema::hasColumn('platform_cadastros', 'latitude') ? 'latitude' : null;
        $longitudeColumn = Schema::hasColumn('platform_cadastros', 'longitude') ? 'longitude' : null;
        $fotoColumn = Schema::hasColumn('platform_cadastros', 'foto_url') ? 'foto_url' : null;

        $columns = [
            'id', 'uuid', 'nome', 'email', 'telefone', 'whatsapp', 'empresa', 'cargo',
            'cep', 'bairro', 'cidade', 'estado', 'status', 'crea', 'crt', 'cft',
            'anos_experiencia', 'raio_atendimento_km', 'especialidades', 'experiencia',
            'categorias', 'certificados', 'portfolio', 'metadados',
        ];

        if ($latitudeColumn) {
            $columns[] = $latitudeColumn;
        }

        if ($longitudeColumn) {
            $columns[] = $longitudeColumn;
        }

        if ($fotoColumn) {
            $columns[] = $fotoColumn;
        }

        $tecnicos = $query
            ->orderBy('estado')
            ->orderBy('cidade')
            ->orderBy('nome')
            ->limit(2000)
            ->get($columns)
            ->map(function ($tecnico) use ($latitudeColumn, $longitudeColumn, $fotoColumn) {
                $metadados = $this->jsonCampo($tecnico->metadados ?? null);
                $lat = $latitudeColumn ? $this->numeroOuNull($tecnico->{$latitudeColumn} ?? null) : null;
                $lng = $longitudeColumn ? $this->numeroOuNull($tecnico->{$longitudeColumn} ?? null) : null;
                $aproximada = ! $this->coordenadaValida($lat, $lng);

                if ($aproximada) {
                    [$lat, $lng] = $this->coordenadaAproximada(
                        (string) ($tecnico->estado ?? ''),
                        (string) ($tecnico->cidade ?? ''),
                        (int) $tecnico->id
                    );
                }

                return [
                    'id' => $tecnico->id,
                    'uuid' => $tecnico->uuid,
                    'nome' => $tecnico->nome,
                    'email' => $tecnico->email,
                    'telefone' => $tecnico->telefone,
                    'whatsapp' => $tecnico->whatsapp,
                    'empresa' => $tecnico->empresa,
                    'cargo' => $tecnico->cargo,
                    'cep' => $tecnico->cep,
                    'bairro' => $tecnico->bairro,
                    'cidade' => $tecnico->cidade,
                    'estado' => strtoupper((string) ($tecnico->estado ?? '')),
                    'status' => $tecnico->status,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'coordenada_aproximada' => $aproximada,
                    'foto_url' => $fotoColumn
                        ? ($tecnico->{$fotoColumn} ?? null)
                        : ($metadados['foto_url'] ?? $metadados['avatar'] ?? null),
                    'qualificacoes' => [
                        'crea' => $tecnico->crea,
                        'crt' => $tecnico->crt,
                        'cft' => $tecnico->cft,
                        'anos_experiencia' => $tecnico->anos_experiencia,
                        'raio_atendimento_km' => $tecnico->raio_atendimento_km,
                        'especialidades' => $this->listaCampo($tecnico->especialidades),
                        'experiencia' => $tecnico->experiencia,
                        'categorias' => $this->jsonCampo($tecnico->categorias),
                    ],
                    'certificados' => $this->jsonCampo($tecnico->certificados),
                    'portfolio' => $this->jsonCampo($tecnico->portfolio),
                ];
            })
            ->values();

        $pontos = $tecnicos
            ->groupBy(fn ($item) => ($item['estado'] ?: 'UF') . '|' . mb_strtoupper((string) ($item['cidade'] ?: 'Sem cidade')))
            ->map(function ($grupo) {
                $primeiro = $grupo->first();

                return [
                    'id' => ($primeiro['estado'] ?: 'UF') . '-' . Str::slug((string) ($primeiro['cidade'] ?: 'sem-cidade')),
                    'cidade' => $primeiro['cidade'] ?: 'Sem cidade',
                    'estado' => $primeiro['estado'],
                    'bairro' => $primeiro['bairro'] ?? null,
                    'latitude' => $primeiro['latitude'],
                    'longitude' => $primeiro['longitude'],
                    'total' => $grupo->count(),
                    'aprovados' => $grupo->where('status', 'aprovado')->count(),
                    'pendentes' => $grupo->where('status', 'pendente')->count(),
                    'tecnicos' => $grupo->take(20)->values(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $porEstado = $tecnicos
            ->groupBy(fn ($item) => $item['estado'] ?: 'Sem UF')
            ->map(fn ($grupo, $estado) => ['estado' => $estado, 'total' => $grupo->count()])
            ->sortByDesc('total')
            ->values();

        $porCidade = $pontos
            ->map(fn ($ponto) => [
                'cidade' => $ponto['cidade'],
                'estado' => $ponto['estado'],
                'total' => $ponto['total'],
            ])
            ->take(12)
            ->values();

        return response()->json([
            'resumo' => [
                'total_tecnicos' => $tecnicos->count(),
                'pontos' => $pontos->count(),
                'estados' => $porEstado->count(),
                'aprovados' => $tecnicos->where('status', 'aprovado')->count(),
                'pendentes' => $tecnicos->where('status', 'pendente')->count(),
            ],
            'pontos' => $pontos,
            'tecnicos' => $tecnicos,
            'rankings' => [
                'por_estado' => $porEstado,
                'por_cidade' => $porCidade,
            ],
        ]);
    }

    public function mapaEquipe(Request $request): JsonResponse
    {
        $tipos = $request->filled('tipo')
            ? [strtolower((string) $request->tipo)]
            : ['tecnico', 'vendedor', 'colaborador'];

        $tipos = array_values(array_intersect($tipos, ['tecnico', 'vendedor', 'colaborador']));

        if (! $tipos) {
            $tipos = ['tecnico', 'vendedor', 'colaborador'];
        }

        $query = DB::table('platform_cadastros')
            ->whereNull('deleted_at')
            ->whereIn('tipo_perfil', $tipos);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $columns = [
            'id', 'uuid', 'tipo_perfil', 'nome', 'email', 'telefone', 'whatsapp',
            'empresa', 'cargo', 'bairro', 'cidade', 'estado', 'status',
            'metadados',
        ];

        foreach (['latitude', 'longitude', 'localizacao_tipo', 'localizacao_compartilhada', 'localizacao_atualizada_em'] as $column) {
            if (Schema::hasColumn('platform_cadastros', $column)) {
                $columns[] = $column;
            }
        }

        $equipe = $query
            ->orderBy('tipo_perfil')
            ->orderBy('estado')
            ->orderBy('cidade')
            ->orderBy('nome')
            ->limit(1000)
            ->get($columns)
            ->map(function ($pessoa) {
                $lat = property_exists($pessoa, 'latitude') ? $this->numeroOuNull($pessoa->latitude) : null;
                $lng = property_exists($pessoa, 'longitude') ? $this->numeroOuNull($pessoa->longitude) : null;
                $aproximada = ! $this->coordenadaValida($lat, $lng);

                if ($aproximada) {
                    [$lat, $lng] = $this->coordenadaAproximada(
                        (string) ($pessoa->estado ?? ''),
                        (string) ($pessoa->cidade ?? ''),
                        (int) $pessoa->id
                    );
                }

                return [
                    'id' => $pessoa->id,
                    'uuid' => $pessoa->uuid,
                    'tipo_perfil' => $pessoa->tipo_perfil,
                    'nome' => $pessoa->nome,
                    'email' => $pessoa->email,
                    'telefone' => $pessoa->telefone,
                    'whatsapp' => $pessoa->whatsapp,
                    'empresa' => $pessoa->empresa,
                    'cargo' => $pessoa->cargo,
                    'bairro' => $pessoa->bairro,
                    'cidade' => $pessoa->cidade,
                    'estado' => strtoupper((string) ($pessoa->estado ?? '')),
                    'status' => $pessoa->status,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'localizacao_tipo' => property_exists($pessoa, 'localizacao_tipo')
                        ? ($pessoa->localizacao_tipo ?: ($aproximada ? 'aproximada_cidade' : 'exata_gps'))
                        : ($aproximada ? 'aproximada_cidade' : 'exata_gps'),
                    'localizacao_compartilhada' => property_exists($pessoa, 'localizacao_compartilhada')
                        ? (bool) $pessoa->localizacao_compartilhada
                        : false,
                    'localizacao_atualizada_em' => property_exists($pessoa, 'localizacao_atualizada_em')
                        ? $pessoa->localizacao_atualizada_em
                        : null,
                    'coordenada_aproximada' => $aproximada,
                ];
            })
            ->values();

        $porTipo = $equipe
            ->groupBy('tipo_perfil')
            ->map(fn ($grupo, $tipo) => ['tipo' => $tipo, 'total' => $grupo->count()])
            ->values();

        $porCidade = $equipe
            ->groupBy(fn ($item) => ($item['estado'] ?: 'UF') . '|' . mb_strtoupper((string) ($item['cidade'] ?: 'Sem cidade')))
            ->map(function ($grupo) {
                $primeiro = $grupo->first();

                return [
                    'cidade' => $primeiro['cidade'] ?: 'Sem cidade',
                    'estado' => $primeiro['estado'],
                    'total' => $grupo->count(),
                ];
            })
            ->sortByDesc('total')
            ->take(10)
            ->values();

        return response()->json([
            'resumo' => [
                'total' => $equipe->count(),
                'colaboradores' => $equipe->where('tipo_perfil', 'colaborador')->count(),
                'tecnicos' => $equipe->where('tipo_perfil', 'tecnico')->count(),
                'com_localizacao_exata' => $equipe->where('coordenada_aproximada', false)->count(),
                'aproximados' => $equipe->where('coordenada_aproximada', true)->count(),
            ],
            'pontos' => $equipe,
            'rankings' => [
                'por_tipo' => $porTipo,
                'por_cidade' => $porCidade,
            ],
        ]);
    }

    public function analisar(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['aprovado', 'rejeitado', 'suspenso', 'pendente'])],
            'observacao_analise' => 'nullable|string|max:5000',
        ]);

        $cadastro = DB::table('platform_cadastros')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($cadastro, 404, 'Cadastro não encontrado.');

        DB::transaction(function () use ($cadastro, $data): void {
            $ativo = $data['status'] === 'aprovado';

            DB::table('platform_cadastros')->where('id', $cadastro->id)->update([
                'status' => $data['status'],
                'observacao_analise' => $data['observacao_analise'] ?? null,
                'analisado_por' => auth()->id(),
                'analisado_em' => now(),
                'updated_at' => now(),
            ]);

            if ($cadastro->usuario_id) {
                DB::table('usuarios')->where('id', $cadastro->usuario_id)->update([
                    'ativo' => $ativo,
                    'updated_at' => now(),
                ]);

                DB::table('platform_perfis_usuario')->updateOrInsert(
                    ['usuario_id' => $cadastro->usuario_id, 'tipo_perfil' => $cadastro->tipo_perfil],
                    [
                        'cadastro_id' => $cadastro->id,
                        'status' => $ativo ? 'ativo' : $data['status'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return response()->json(['message' => 'Status atualizado com sucesso.']);
    }

    public function excluir(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['motivo' => 'nullable|string|max:1000']);

        $cadastro = DB::table('platform_cadastros')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($cadastro, 404, 'Cadastro não encontrado.');

        DB::transaction(function () use ($cadastro, $data, $request): void {
            DB::table('platform_cadastros')->where('id', $cadastro->id)->update([
                'status' => 'excluido',
                'deleted_at' => now(),
                'deleted_by' => auth()->id(),
                'delete_reason' => $data['motivo'] ?? null,
                'updated_at' => now(),
            ]);

            if ($cadastro->usuario_id) {
                DB::table('usuarios')->where('id', $cadastro->usuario_id)->update([
                    'ativo' => false,
                    'updated_at' => now(),
                ]);
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\Usuario')
                    ->where('tokenable_id', $cadastro->usuario_id)
                    ->delete();
            }

            $this->auditar($cadastro, 'soft_delete', $data['motivo'] ?? null, $request);
        });

        return response()->json(['message' => 'Usuário movido para a lixeira e login bloqueado.']);
    }

    public function restaurar(Request $request, int $id): JsonResponse
    {
        $cadastro = DB::table('platform_cadastros')
            ->where('id', $id)
            ->whereNotNull('deleted_at')
            ->first();

        abort_unless($cadastro, 404, 'Cadastro excluído não encontrado.');

        DB::transaction(function () use ($cadastro, $request): void {
            DB::table('platform_cadastros')->where('id', $cadastro->id)->update([
                'status' => 'aprovado',
                'deleted_at' => null,
                'deleted_by' => null,
                'delete_reason' => null,
                'updated_at' => now(),
            ]);

            if ($cadastro->usuario_id) {
                DB::table('usuarios')->where('id', $cadastro->usuario_id)->update([
                    'ativo' => true,
                    'updated_at' => now(),
                ]);
            }

            $this->auditar($cadastro, 'restore', null, $request);
        });

        return response()->json(['message' => 'Usuário restaurado e login reativado.']);
    }

    public function excluirDefinitivamente(Request $request, int $id): JsonResponse
    {
        abort_unless($this->ehMaster(), 403, 'Somente o Administrador Master pode excluir definitivamente.');

        $data = $request->validate([
            'confirmacao' => ['required', Rule::in(['EXCLUIR'])],
            'motivo' => 'nullable|string|max:1000',
        ]);

        $cadastro = DB::table('platform_cadastros')
            ->where('id', $id)
            ->whereNotNull('deleted_at')
            ->first();

        abort_unless($cadastro, 404, 'Cadastro excluído não encontrado.');

        DB::transaction(function () use ($cadastro, $data, $request): void {
            $this->auditar($cadastro, 'force_delete', $data['motivo'] ?? null, $request);

            if ($cadastro->usuario_id) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\Usuario')
                    ->where('tokenable_id', $cadastro->usuario_id)
                    ->delete();

                DB::table('platform_perfis_usuario')
                    ->where('usuario_id', $cadastro->usuario_id)
                    ->delete();
            }

            DB::table('platform_cadastros')->where('id', $cadastro->id)->delete();

            if ($cadastro->usuario_id) {
                $outroCadastro = DB::table('platform_cadastros')
                    ->where('usuario_id', $cadastro->usuario_id)
                    ->exists();

                if (!$outroCadastro) {
                    DB::table('usuarios')->where('id', $cadastro->usuario_id)->delete();
                }
            }
        });

        return response()->json(['message' => 'Usuário excluído definitivamente.']);
    }

    private function salvarUsuario(
        int $empresaId,
        string $tipo,
        string $nome,
        string $email,
        ?string $telefone,
        ?string $cargo,
        ?string $senha,
        ?string $socialProvider,
        bool $ativo,
    ): int {
        $email = mb_strtolower(trim($email));
        $existente = DB::table('usuarios')->whereRaw('LOWER(email) = ?', [$email])->first();
        $perfilId = $this->perfilId($empresaId, $tipo);

        $payload = [
            'empresa_id' => $empresaId,
            'perfil_id' => $perfilId,
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone,
            'cargo' => $cargo,
            'ativo' => $ativo,
            'updated_at' => now(),
        ];

        if ($senha) {
            $payload['senha'] = Hash::make($senha);
        } elseif (!$existente) {
            // A coluna senha é obrigatória; o OAuth assumirá o acesso posteriormente.
            $payload['senha'] = Hash::make(Str::random(64));
        }

        if ($existente) {
            DB::table('usuarios')->where('id', $existente->id)->update($payload);
            return (int) $existente->id;
        }

        $payload['uuid'] = (string) Str::uuid();
        $payload['created_at'] = now();

        return (int) DB::table('usuarios')->insertGetId($payload);
    }

    private function perfilId(int $empresaId, string $tipo): int
    {
        $nome = match ($tipo) {
            'tecnico' => 'Técnico Marketplace',
            'cliente' => 'Cliente Marketplace',
            'vendedor' => 'Vendedor Parceiro',
            'colaborador' => 'Colaborador Marketplace',
        };

        $perfil = DB::table('perfis')
            ->where('empresa_id', $empresaId)
            ->where('nome', $nome)
            ->first();

        if ($perfil) {
            return (int) $perfil->id;
        }

        return (int) DB::table('perfis')->insertGetId([
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'descricao' => 'Perfil criado pelo Identity Core da IntelbrasTech Platform',
            'admin' => false,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function empresaPrincipalId(): int
    {
        $id = DB::table('empresas')->orderByDesc('ativo')->orderBy('id')->value('id');

        if (!$id) {
            throw new RuntimeException('Nenhuma empresa cadastrada no ERP.');
        }

        return (int) $id;
    }

    private function auditar(object $cadastro, string $acao, ?string $motivo, Request $request): void
    {
        if (!Schema::hasTable('platform_user_deletion_logs')) {
            return;
        }

        DB::table('platform_user_deletion_logs')->insert([
            'cadastro_id' => $cadastro->id,
            'usuario_id' => $cadastro->usuario_id,
            'acao' => $acao,
            'motivo' => $motivo,
            'executado_por' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ehMaster(): bool
    {
        $usuario = auth()->user();

        if (!$usuario) {
            return false;
        }

        if ($usuario->perfil?->admin && Str::contains(Str::lower((string) $usuario->perfil?->nome), 'master')) {
            return true;
        }

        return in_array((int) $usuario->id, [1, 2, 3], true)
            && (bool) $usuario->perfil?->admin;
    }

    private function filtrarColunas(string $tabela, array $dados): array
    {
        $colunas = array_flip(Schema::getColumnListing($tabela));

        return array_filter(
            $dados,
            static fn ($valor, $coluna) => array_key_exists($coluna, $colunas),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function jsonCampo(mixed $valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        if ($valor === null || $valor === '') {
            return [];
        }

        $decoded = json_decode((string) $valor, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return $this->listaCampo((string) $valor);
    }

    private function listaCampo(?string $valor): array
    {
        if (! $valor) {
            return [];
        }

        return collect(preg_split('/[\r\n;,]+/', $valor))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function numeroOuNull(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $numero = (float) str_replace(',', '.', (string) $valor);

        return is_finite($numero) ? $numero : null;
    }

    private function coordenadaValida(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null
            && $lat >= -34.5 && $lat <= 6
            && $lng >= -74 && $lng <= -30;
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
        $latOffset = (($hash % 1000) / 1000 - 0.5) * 1.4;
        $lngOffset = ((intdiv($hash, 1000) % 1000) / 1000 - 0.5) * 1.4;

        return [round($base[0] + $latOffset, 6), round($base[1] + $lngOffset, 6)];
    }
}
