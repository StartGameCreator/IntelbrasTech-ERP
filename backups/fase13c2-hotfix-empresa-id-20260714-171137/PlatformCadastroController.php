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
        $defaults = [
            'Técnico de Informática',
            'Técnico de TV',
            'Técnico Eletrotécnico',
            'Automação Residencial',
            'Automação Industrial',
            'Bombeiro Hidráulico',
            'Mestre de Obras',
            'Pedreiro',
            'Ladrilheiro',
            'Segurança Eletrônica',
            'Eletricista',
            'Energia Solar',
            'Vendedor Técnico',
            'Técnico de Portões',
            'Técnico de Impressoras',
        ];

        foreach ($defaults as $index => $nome) {
            DB::table('platform_categorias_tecnicas')->updateOrInsert(
                ['slug' => Str::slug($nome)],
                [
                    'nome' => $nome,
                    'ativo' => true,
                    'ordem' => $index + 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return response()->json(
            DB::table('platform_categorias_tecnicas')
                ->where('ativo', true)
                ->orderBy('ordem')
                ->orderBy('nome')
                ->get(['id', 'nome', 'slug', 'descricao'])
        );
    }

    public function cadastrar(Request $request, string $tipo): JsonResponse
    {
        abort_unless(in_array($tipo, ['tecnico', 'cliente', 'colaborador'], true), 404);

        $rules = [
            'nome' => 'required|string|max:180',
            'nome_exibicao' => 'nullable|string|max:180',
            'email' => [
                'required',
                'email',
                'max:180',
                Rule::unique('platform_cadastros', 'email')
                    ->where(fn ($query) => $query
                        ->where('tipo_perfil', $tipo)
                        ->whereNull('deleted_at')),
            ],
            'telefone' => 'nullable|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'tipo_pessoa' => ['nullable', Rule::in(['PF', 'PJ'])],
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
            'data_nascimento' => 'nullable|date',
            'motivacao' => 'nullable|string|max:5000',
            'senha' => 'required_without:social_provider|nullable|string|min:8|confirmed',
            'social_provider' => ['nullable', Rule::in(['google', 'microsoft', 'apple', 'facebook'])],
        ];

        if ($tipo === 'tecnico') {
            $rules = array_merge($rules, [
                'crea' => 'nullable|string|max:50',
                'crt' => 'nullable|string|max:50',
                'cft' => 'nullable|string|max:50',
                'anos_experiencia' => 'nullable|integer|min:0|max:80',
                'raio_atendimento_km' => 'nullable|integer|min:1|max:1000',
                'especialidades' => 'required|string|max:5000',
                'experiencia' => 'nullable|string|max:10000',
                'categorias' => 'required|array|min:1',
                'categorias.*' => 'integer|exists:platform_categorias_tecnicas,id',
            ]);
        }

        if ($tipo === 'colaborador') {
            $rules = array_merge($rules, [
                'nivel_colaborador' => [
                    'required',
                    Rule::in(['administrador_jr', 'administrador_pleno', 'administrador_senior']),
                ],
                'cargo' => 'required|string|max:120',
                'motivacao' => 'required|string|min:30|max:5000',
            ]);
        }

        $data = $request->validate($rules);

        foreach (['cpf', 'cnpj', 'cep', 'telefone', 'whatsapp'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = preg_replace('/\D+/', '', (string) $data[$field]) ?: null;
            }
        }

        $senha = $data['senha'] ?? null;
        unset($data['senha'], $data['senha_confirmation']);

        return DB::transaction(function () use ($data, $senha, $tipo): JsonResponse {
            $empresaId = $this->resolverEmpresaId($data['empresa_id'] ?? null);

            $usuarioId = $this->criarOuAtualizarUsuario(
                nome: $data['nome'],
                email: $data['email'],
                senha: $senha,
                tipo: $tipo,
                socialProvider: $data['social_provider'] ?? null,
                empresaId: $empresaId,
            );

            $cadastroData = $data;

            if (isset($cadastroData['categorias'])) {
                $cadastroData['categorias'] = json_encode($cadastroData['categorias']);
            }

            if ($senha && Schema::hasColumn('platform_cadastros', 'senha_hash')) {
                $cadastroData['senha_hash'] = Hash::make($senha);
            }

            if (Schema::hasColumn('platform_cadastros', 'usuario_id')) {
                $cadastroData['usuario_id'] = $usuarioId;
            }

            if (Schema::hasColumn('platform_cadastros', 'empresa_id')) {
                $cadastroData['empresa_id'] = $empresaId;
            }

            $id = DB::table('platform_cadastros')->insertGetId(array_merge(
                $this->somenteColunasExistentes('platform_cadastros', $cadastroData),
                [
                    'uuid' => (string) Str::uuid(),
                    'tipo_perfil' => $tipo,
                    'status' => $tipo === 'colaborador' ? 'pendente' : 'aprovado',
                    'tipo_pessoa' => $data['tipo_pessoa'] ?? 'PF',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ));

            if (Schema::hasTable('platform_perfis_usuario')) {
                DB::table('platform_perfis_usuario')->updateOrInsert(
                    [
                        'usuario_id' => $usuarioId,
                        'tipo_perfil' => $tipo,
                    ],
                    [
                        'cadastro_id' => $id,
                        'status' => $tipo === 'colaborador' ? 'pendente' : 'ativo',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            return response()->json([
                'message' => match ($tipo) {
                    'tecnico' => 'Cadastro técnico concluído. Sua conta já pode ser usada no login.',
                    'cliente' => 'Cadastro de cliente concluído. Sua conta já pode ser usada no login.',
                    'colaborador' => 'Solicitação recebida. O acesso será liberado após aprovação do Administrador Master.',
                },
                'cadastro_id' => $id,
                'usuario_id' => $usuarioId,
                'empresa_id' => $empresaId,
                'status' => $tipo === 'colaborador' ? 'pendente' : 'aprovado',
                'login_disponivel' => $tipo !== 'colaborador',
            ], 201);
        });
    }

    public function painel(Request $request): JsonResponse
    {
        $query = DB::table('platform_cadastros')->whereNull('deleted_at');

        if ($request->filled('tipo')) {
            $query->where('tipo_perfil', $request->tipo);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'resumo' => [
                'total' => DB::table('platform_cadastros')->whereNull('deleted_at')->count(),
                'tecnicos' => DB::table('platform_cadastros')->whereNull('deleted_at')->where('tipo_perfil', 'tecnico')->count(),
                'clientes' => DB::table('platform_cadastros')->whereNull('deleted_at')->where('tipo_perfil', 'cliente')->count(),
                'colaboradores' => DB::table('platform_cadastros')->whereNull('deleted_at')->where('tipo_perfil', 'colaborador')->count(),
                'pendentes' => DB::table('platform_cadastros')->whereNull('deleted_at')->where('status', 'pendente')->count(),
            ],
            'cadastros' => $query->orderByDesc('id')->limit(200)->get(),
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
            DB::table('platform_cadastros')->where('id', $cadastro->id)->update([
                'status' => $data['status'],
                'observacao_analise' => $data['observacao_analise'] ?? null,
                'analisado_por' => auth()->id(),
                'analisado_em' => now(),
                'updated_at' => now(),
            ]);

            if ($cadastro->usuario_id) {
                $this->atualizarStatusUsuario(
                    usuarioId: (int) $cadastro->usuario_id,
                    ativo: $data['status'] === 'aprovado',
                    status: $data['status'] === 'aprovado' ? 'ativo' : $data['status'],
                );
            }

            if (Schema::hasTable('platform_perfis_usuario') && $cadastro->usuario_id) {
                DB::table('platform_perfis_usuario')->updateOrInsert(
                    [
                        'usuario_id' => $cadastro->usuario_id,
                        'tipo_perfil' => $cadastro->tipo_perfil,
                    ],
                    [
                        'cadastro_id' => $cadastro->id,
                        'status' => $data['status'] === 'aprovado' ? 'ativo' : $data['status'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return response()->json(['message' => 'Cadastro analisado com sucesso.']);
    }

    private function criarOuAtualizarUsuario(
        string $nome,
        string $email,
        ?string $senha,
        string $tipo,
        ?string $socialProvider,
        int $empresaId,
    ): int {
        $table = $this->tabelaUsuarios();

        $existing = DB::table($table)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        $payload = [
            'uuid' => (string) Str::uuid(),
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'name' => $nome,
            'email' => mb_strtolower(trim($email)),
            'tipo' => $tipo,
            'tipo_usuario' => $tipo,
            'perfil' => $tipo,
            'role' => $tipo,
            'ativo' => $tipo !== 'colaborador',
            'active' => $tipo !== 'colaborador',
            'status' => $tipo === 'colaborador' ? 'pendente' : 'ativo',
            'social_provider' => $socialProvider,
            'email_verified_at' => $socialProvider ? now() : null,
            'updated_at' => now(),
        ];

        if ($senha) {
            $hash = Hash::make($senha);
            $payload['senha'] = $hash;
            $payload['password'] = $hash;
        }

        $payload = $this->somenteColunasExistentes($table, $payload);

        if ($existing) {
            unset($payload['uuid']);
            DB::table($table)->where('id', $existing->id)->update($payload);
            return (int) $existing->id;
        }

        if (!$senha && !$socialProvider) {
            throw new RuntimeException('Não foi possível criar a conta: senha ou login social são obrigatórios.');
        }

        if (Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = now();
        }

        $id = DB::table($table)->insertGetId($payload);

        return (int) $id;
    }

    private function atualizarStatusUsuario(int $usuarioId, bool $ativo, string $status): void
    {
        $table = $this->tabelaUsuarios();

        $payload = $this->somenteColunasExistentes($table, [
            'ativo' => $ativo,
            'active' => $ativo,
            'status' => $status,
            'updated_at' => now(),
        ]);

        if ($payload !== []) {
            DB::table($table)->where('id', $usuarioId)->update($payload);
        }
    }

    private function resolverEmpresaId(?int $empresaId = null): int
    {
        if (!Schema::hasTable('empresas')) {
            throw new RuntimeException('Tabela empresas não encontrada.');
        }

        if ($empresaId && DB::table('empresas')->where('id', $empresaId)->exists()) {
            return $empresaId;
        }

        $empresa = DB::table('empresas')
            ->orderByDesc('ativo')
            ->orderBy('id')
            ->first();

        if (!$empresa) {
            throw new RuntimeException(
                'Nenhuma empresa cadastrada. Cadastre a empresa principal antes de criar usuários.'
            );
        }

        return (int) $empresa->id;
    }

    private function tabelaUsuarios(): string
    {
        foreach (['usuarios', 'users'] as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        throw new RuntimeException('Tabela de usuários não encontrada. Era esperado "usuarios" ou "users".');
    }

    private function somenteColunasExistentes(string $table, array $payload): array
    {
        $columns = array_flip(Schema::getColumnListing($table));

        return array_filter(
            $payload,
            static fn ($value, $column) => array_key_exists($column, $columns),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
