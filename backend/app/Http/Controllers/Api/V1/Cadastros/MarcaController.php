<?php

namespace App\Http\Controllers\Api\V1\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Str;

class MarcaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('produtos.view');

        $marcas = Marca::where('ativo', true)
            ->when($request->busca, fn($q, $v) => $q->whereLike('nome', "%{$v}%"))
            ->orderBy('nome')
            ->get();

        return response()->json($marcas);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('produtos.create');

        $dados = $request->validate(['nome' => 'required|string|max:100']);
        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados['slug']       = Str::slug($dados['nome']);

        $marca = Marca::create($dados);

        return response()->json(['message' => 'Marca criada.', 'marca' => $marca], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');

        $marca = Marca::findOrFail($id);
        $marca->update($request->validate([
            'nome'  => 'sometimes|string|max:100',
            'ativo' => 'sometimes|boolean',
        ]));

        return response()->json(['message' => 'Marca atualizada.', 'marca' => $marca]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('produtos.delete');

        Marca::findOrFail($id)->delete();

        return response()->json(['message' => 'Marca excluída.']);
    }
}
