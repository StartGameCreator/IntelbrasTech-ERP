<?php

namespace App\Http\Controllers\Api\V1\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Fornecedor;
use Illuminate\Http\{Request, JsonResponse};

class FornecedorController extends Controller
{
    // ── GET /fornecedores ──────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('fornecedores.view');

        $q = Fornecedor::when($request->busca, fn($q, $v) => $q->busca($v))
            ->when($request->tipo, fn($q, $v) => $q->where('tipo', $v))
            ->when($request->ativo !== null, fn($q) => $q->where('ativo', $request->boolean('ativo')))
            ->when($request->estado, fn($q, $v) => $q->where('estado', $v))
            ->when($request->cidade, fn($q, $v) => $q->whereLike('cidade', "%{$v}%"))
            ->orderBy($request->get('order_by', 'razao_social'), $request->get('order', 'asc'));

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /fornecedores ─────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fornecedores.create');

        $dados = $this->validarDados($request);
        $dados['empresa_id'] = auth()->user()->empresa_id;

        $fornecedor = Fornecedor::create($dados);

        return response()->json([
            'message'    => 'Fornecedor cadastrado com sucesso.',
            'fornecedor' => $fornecedor,
        ], 201);
    }

    // ── GET /fornecedores/{id} ─────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('fornecedores.view');

        $fornecedor = Fornecedor::withCount('produtos')->findOrFail($id);

        $fornecedor->total_comprado = (float) $fornecedor->pedidosCompra()
            ->where('status', 'recebido')
            ->sum('total');

        $fornecedor->contas_pagar_em_aberto = (float) $fornecedor->contasPagar()
            ->whereNotIn('status', ['pago', 'cancelado'])
            ->sum('valor');

        return response()->json($fornecedor);
    }

    // ── PUT /fornecedores/{id} ─────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fornecedores.edit');

        $fornecedor = Fornecedor::findOrFail($id);
        $dados = $this->validarDados($request, atualizando: true);

        $fornecedor->update($dados);

        return response()->json([
            'message'    => 'Fornecedor atualizado com sucesso.',
            'fornecedor' => $fornecedor,
        ]);
    }

    // ── DELETE /fornecedores/{id} ────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fornecedores.delete');

        $fornecedor = Fornecedor::findOrFail($id);

        if ($fornecedor->produtos()->exists()) {
            return response()->json(['message' => 'Fornecedor possui produtos vinculados e não pode ser excluído. Desative-o em vez disso.'], 422);
        }
        if ($fornecedor->pedidosCompra()->exists()) {
            return response()->json(['message' => 'Fornecedor possui pedidos de compra vinculados e não pode ser excluído.'], 422);
        }

        $fornecedor->delete();

        return response()->json(['message' => 'Fornecedor excluído com sucesso.']);
    }

    // ── GET /fornecedores/ranking ────────────────────────────────
    // Ranking por valor total comprado (pedidos de compra recebidos).

    public function ranking(Request $request): JsonResponse
    {
        $this->authorize('fornecedores.view');

        $limite = min((int) $request->get('limite', 20), 100);

        $ranking = Fornecedor::withSum(['pedidosCompra as total_comprado' => function ($q) {
                $q->where('status', 'recebido');
            }], 'total')
            ->withCount('pedidosCompra')
            ->where('ativo', true)
            ->orderByDesc('total_comprado')
            ->limit($limite)
            ->get()
            ->map(fn ($f) => [
                'id'                 => $f->id,
                'razao_social'       => $f->razao_social,
                'nome_fantasia'      => $f->nome_fantasia,
                'avaliacao'          => $f->avaliacao,
                'total_comprado'     => round((float) $f->total_comprado, 2),
                'total_pedidos'      => $f->pedidos_compra_count,
                'prazo_entrega_dias' => $f->prazo_entrega_dias,
            ]);

        return response()->json($ranking);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function validarDados(Request $request, bool $atualizando = false): array
    {
        $regraBase = $atualizando ? 'sometimes' : 'required';

        return $request->validate([
            'tipo'                  => "$regraBase|in:F,J",
            'razao_social'          => "$regraBase|string|max:200",
            'nome_fantasia'         => 'nullable|string|max:200',
            'cpf_cnpj'              => 'nullable|string|max:18',
            'ie'                    => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:150',
            'telefone'              => 'nullable|string|max:20',
            'celular'               => 'nullable|string|max:20',
            'whatsapp'              => 'nullable|string|max:20',
            'site'                  => 'nullable|string|max:200',
            'cep'                   => 'nullable|string|max:9',
            'logradouro'            => 'nullable|string|max:200',
            'numero'                => 'nullable|string|max:10',
            'complemento'           => 'nullable|string|max:100',
            'bairro'                => 'nullable|string|max:100',
            'cidade'                => 'nullable|string|max:100',
            'estado'                => 'nullable|string|size:2',
            'prazo_entrega_dias'    => 'nullable|integer|min:0',
            'prazo_pagamento_dias'  => 'nullable|integer|min:0',
            'desconto_padrao'       => 'nullable|numeric|min:0|max:100',
            'avaliacao'             => 'nullable|integer|min:0|max:5',
            'banco'                 => 'nullable|string|max:100',
            'agencia'               => 'nullable|string|max:10',
            'conta'                 => 'nullable|string|max:20',
            'pix'                   => 'nullable|string|max:100',
            'contato_principal'     => 'nullable|string|max:150',
            'observacoes'           => 'nullable|string',
            'ativo'                 => 'nullable|boolean',
        ]);
    }
}
