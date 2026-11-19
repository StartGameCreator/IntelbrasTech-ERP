<?php

namespace App\Http\Controllers\Api\V1\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\UnidadeMedida;
use Illuminate\Http\{Request, JsonResponse};

class UnidadeMedidaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('produtos.view');

        $unidades = UnidadeMedida::orderBy('sigla')->get();

        return response()->json($unidades);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('produtos.create');

        $dados = $request->validate([
            'sigla'      => 'required|string|max:10',
            'descricao'  => 'nullable|string|max:50',
        ]);
        $dados['empresa_id'] = auth()->user()->empresa_id;

        $unidade = UnidadeMedida::create($dados);

        return response()->json(['message' => 'Unidade de medida criada.', 'unidade' => $unidade], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('produtos.delete');

        UnidadeMedida::findOrFail($id)->delete();

        return response()->json(['message' => 'Unidade de medida excluída.']);
    }
}
