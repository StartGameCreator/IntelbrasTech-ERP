<?php

namespace App\Http\Controllers\Api\V1\Estoque;

use App\Http\Controllers\Controller;
use App\Models\Deposito;
use Illuminate\Http\{Request, JsonResponse};

class DepositoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('estoque.view');

        $depositos = Deposito::when($request->ativo !== null, fn($q) => $q->where('ativo', $request->boolean('ativo')))
            ->when($request->busca, fn($q, $v) => $q->whereLike('nome', "%{$v}%"))
            ->orderByDesc('principal')
            ->orderBy('nome')
            ->get();

        return response()->json($depositos);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('estoque.create');

        $dados = $request->validate([
            'nome'         => 'required|string|max:100',
            'descricao'    => 'nullable|string',
            'responsavel'  => 'nullable|string|max:150',
            'cep'          => 'nullable|string|max:9',
            'logradouro'   => 'nullable|string|max:200',
            'numero'       => 'nullable|string|max:10',
            'bairro'       => 'nullable|string|max:100',
            'cidade'       => 'nullable|string|max:100',
            'estado'       => 'nullable|string|max:2',
            'principal'    => 'nullable|boolean',
        ]);

        $dados['empresa_id'] = auth()->user()->empresa_id;

        if (! empty($dados['principal'])) {
            Deposito::where('empresa_id', $dados['empresa_id'])->update(['principal' => false]);
        }

        $deposito = Deposito::create($dados);

        return response()->json(['message' => 'Depósito criado com sucesso.', 'deposito' => $deposito], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('estoque.view');

        $deposito = Deposito::findOrFail($id);

        return response()->json($deposito);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('estoque.edit');

        $deposito = Deposito::findOrFail($id);

        $dados = $request->validate([
            'nome'         => 'sometimes|string|max:100',
            'descricao'    => 'nullable|string',
            'responsavel'  => 'nullable|string|max:150',
            'cep'          => 'nullable|string|max:9',
            'logradouro'   => 'nullable|string|max:200',
            'numero'       => 'nullable|string|max:10',
            'bairro'       => 'nullable|string|max:100',
            'cidade'       => 'nullable|string|max:100',
            'estado'       => 'nullable|string|max:2',
            'principal'    => 'nullable|boolean',
            'ativo'        => 'nullable|boolean',
        ]);

        if (! empty($dados['principal'])) {
            Deposito::where('empresa_id', $deposito->empresa_id)->where('id', '!=', $id)->update(['principal' => false]);
        }

        $deposito->update($dados);

        return response()->json(['message' => 'Depósito atualizado com sucesso.', 'deposito' => $deposito]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('estoque.delete');

        $deposito = Deposito::findOrFail($id);

        if ($deposito->principal) {
            return response()->json(['message' => 'O depósito principal não pode ser excluído.'], 422);
        }

        if ($deposito->estoque()->where('quantidade', '>', 0)->exists()) {
            return response()->json(['message' => 'Depósito possui saldo em estoque e não pode ser excluído.'], 422);
        }

        $deposito->delete();

        return response()->json(['message' => 'Depósito excluído com sucesso.']);
    }
}
