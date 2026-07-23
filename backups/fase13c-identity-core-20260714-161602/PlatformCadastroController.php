<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
            'email' => [
                'required',
                'email',
                'max:180',
                Rule::unique('platform_cadastros', 'email')
                    ->where(fn ($q) => $q->where('tipo_perfil', $tipo)->whereNull('deleted_at')),
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

        if (isset($data['categorias'])) {
            $data['categorias'] = json_encode($data['categorias']);
        }

        $senha = $data['senha'] ?? null;
        unset($data['senha'], $data['senha_confirmation']);

        if ($senha) {
            $data['senha_hash'] = Hash::make($senha);
        }

        $id = DB::table('platform_cadastros')->insertGetId(array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'tipo_perfil' => $tipo,
            'status' => 'pendente',
            'tipo_pessoa' => $data['tipo_pessoa'] ?? 'PF',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return response()->json([
            'message' => match ($tipo) {
                'tecnico' => 'Cadastro técnico recebido. Suas qualificações serão analisadas.',
                'cliente' => 'Cadastro de cliente recebido com sucesso.',
                'colaborador' => 'Solicitação recebida. O Administrador Master fará a análise.',
            },
            'cadastro_id' => $id,
            'status' => 'pendente',
        ], 201);
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

        DB::table('platform_cadastros')->where('id', $id)->update([
            'status' => $data['status'],
            'observacao_analise' => $data['observacao_analise'] ?? null,
            'analisado_por' => auth()->id(),
            'analisado_em' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Cadastro analisado com sucesso.']);
    }
}
