<?php

namespace App\Http\Controllers\Api\V1\MarketplaceTecnicos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarketplaceNucleoController extends Controller
{
    public function publicConfig(): JsonResponse
    {
        return response()->json([
            'categorias' => DB::table('marketplace_categorias')
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'slug']),
        ]);
    }

    public function cadastrarTecnico(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome' => 'required|string|max:180',
            'email' => 'required|email|max:180|unique:marketplace_tecnicos,email',
            'cpf' => 'required|string|max:20',
            'rg' => 'nullable|string|max:30',
            'crea' => 'nullable|string|max:40',
            'crt' => 'nullable|string|max:40',
            'cft' => 'nullable|string|max:40',
            'empresa' => 'nullable|string|max:180',
            'cnpj' => 'nullable|string|max:20',
            'telefone' => 'nullable|string|max:30',
            'whatsapp' => 'required|string|max:30',
            'cep' => 'required|string|max:12',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:30',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'required|string|max:120',
            'estado' => 'required|string|size:2',
            'regiao_atendimento' => 'nullable|string|max:3000',
            'raio_km' => 'nullable|integer|min:1|max:500',
            'experiencia' => 'nullable|string|max:5000',
            'bio' => 'nullable|string|max:5000',
            'categorias' => 'required|array|min:1',
            'categorias.*' => 'integer|exists:marketplace_categorias,id',
        ]);

        $tecnicoId = DB::transaction(function () use ($data) {
            $categorias = $data['categorias'];
            unset($data['categorias']);

            foreach (['cpf', 'cnpj', 'cep'] as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== null) {
                    $data[$field] = preg_replace('/\D+/', '', $data[$field]);
                }
            }

            $id = DB::table('marketplace_tecnicos')->insertGetId(array_merge($data, [
                'uuid' => (string) Str::uuid(),
                'status' => 'pendente',
                'online' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            foreach ($categorias as $index => $categoriaId) {
                DB::table('marketplace_tecnico_categorias')->insert([
                    'tecnico_id' => $id,
                    'categoria_id' => $categoriaId,
                    'principal' => $index === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->auditar('cadastro_publico_tecnico', 'marketplace_tecnicos', $id, [
                'email' => $data['email'],
            ]);

            return $id;
        });

        return response()->json([
            'message' => 'Cadastro recebido. O técnico ficará pendente até a aprovação do Administrador Master.',
            'id' => $tecnicoId,
        ], 201);
    }

    public function cadastrarCliente(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome' => 'required|string|max:180',
            'email' => 'required|email|max:180|unique:marketplace_clientes,email',
            'tipo_pessoa' => ['required', Rule::in(['PF', 'PJ'])],
            'cpf' => 'nullable|string|max:20',
            'cnpj' => 'nullable|string|max:20',
            'empresa' => 'nullable|string|max:180',
            'telefone' => 'nullable|string|max:30',
            'whatsapp' => 'required|string|max:30',
            'cep' => 'required|string|max:12',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:30',
            'complemento' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:255',
            'cidade' => 'required|string|max:120',
            'estado' => 'required|string|size:2',
        ]);

        foreach (['cpf', 'cnpj', 'cep'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = preg_replace('/\D+/', '', $data[$field]);
            }
        }

        $id = DB::table('marketplace_clientes')->insertGetId(array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'status' => 'aprovado',
            'aprovado_em' => now(),
            'aprovado_por' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->auditar('cadastro_publico_cliente', 'marketplace_clientes', $id, [
            'email' => $data['email'],
        ]);

        return response()->json([
            'message' => 'Cadastro concluído. Seu perfil de cliente já está ativo.',
            'status' => 'aprovado',
            'id' => $id,
        ], 201);
    }

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'resumo' => [
                'tecnicos' => DB::table('marketplace_tecnicos')->whereNull('deleted_at')->count(),
                'tecnicos_pendentes' => DB::table('marketplace_tecnicos')->whereNull('deleted_at')->where('status', 'pendente')->count(),
                'tecnicos_aprovados' => DB::table('marketplace_tecnicos')->whereNull('deleted_at')->where('status', 'aprovado')->count(),
                'tecnicos_online' => DB::table('marketplace_tecnicos')->whereNull('deleted_at')->where('online', true)->count(),
                'clientes' => DB::table('marketplace_clientes')->whereNull('deleted_at')->count(),
                'clientes_pendentes' => DB::table('marketplace_clientes')->whereNull('deleted_at')->where('status', 'pendente')->count(),
                'categorias' => DB::table('marketplace_categorias')->where('ativo', true)->count(),
            ],
            'tecnicos' => DB::table('marketplace_tecnicos')
                ->whereNull('deleted_at')
                ->orderByRaw("CASE status WHEN 'pendente' THEN 0 WHEN 'aprovado' THEN 1 ELSE 2 END")
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'clientes' => DB::table('marketplace_clientes')
                ->whereNull('deleted_at')
                ->orderByRaw("CASE status WHEN 'pendente' THEN 0 WHEN 'aprovado' THEN 1 ELSE 2 END")
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'categorias' => DB::table('marketplace_categorias')
                ->orderBy('nome')
                ->get(),
        ]);
    }

    public function atualizarStatusTecnico(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pendente', 'aprovado', 'rejeitado', 'suspenso'])],
            'motivo_rejeicao' => 'nullable|string|max:3000',
        ]);

        $updated = DB::table('marketplace_tecnicos')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'status' => $data['status'],
                'motivo_rejeicao' => $data['motivo_rejeicao'] ?? null,
                'aprovado_em' => $data['status'] === 'aprovado' ? now() : null,
                'aprovado_por' => auth()->id(),
                'updated_at' => now(),
            ]);

        abort_unless($updated, 404, 'Técnico não encontrado.');

        $this->auditar('alteracao_status_tecnico', 'marketplace_tecnicos', $id, $data);

        return response()->json(['message' => 'Status do técnico atualizado.']);
    }

    public function atualizarStatusCliente(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pendente', 'aprovado', 'rejeitado', 'suspenso'])],
            'motivo_rejeicao' => 'nullable|string|max:3000',
        ]);

        $updated = DB::table('marketplace_clientes')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'status' => $data['status'],
                'motivo_rejeicao' => $data['motivo_rejeicao'] ?? null,
                'aprovado_em' => $data['status'] === 'aprovado' ? now() : null,
                'aprovado_por' => auth()->id(),
                'updated_at' => now(),
            ]);

        abort_unless($updated, 404, 'Cliente não encontrado.');

        $this->auditar('alteracao_status_cliente', 'marketplace_clientes', $id, $data);

        return response()->json(['message' => 'Status do cliente atualizado.']);
    }

    private function auditar(string $acao, string $entidade, int $entidadeId, array $dados = []): void
    {
        DB::table('marketplace_auditoria')->insert([
            'usuario_id' => auth()->id(),
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'dados' => json_encode($dados, JSON_UNESCAPED_UNICODE),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
