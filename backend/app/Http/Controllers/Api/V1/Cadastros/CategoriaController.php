<?php

namespace App\Http\Controllers\Api\V1\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\{Request, JsonResponse};

class CategoriaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('produtos.view');

        $categorias = Categoria::where('ativo', true)
            ->when($request->busca, fn($q, $v) => $q->whereLike('nome', "%{$v}%"))
            ->orderBy('nome')
            ->get();

        return response()->json($categorias);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('produtos.create');

        $dados = $request->validate([
            'nome'             => 'required|string|max:100',
            'categoria_pai_id' => 'nullable|exists:categorias,id',
        ]);

        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados['slug']       = \Illuminate\Support\Str::slug($dados['nome']);

        $categoria = Categoria::create($dados);

        return response()->json(['message' => 'Categoria criada.', 'categoria' => $categoria], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');

        $categoria = Categoria::findOrFail($id);
        $dados = $request->validate([
            'nome'  => 'sometimes|string|max:100',
            'ativo' => 'sometimes|boolean',
        ]);

        $categoria->update($dados);

        return response()->json(['message' => 'Categoria atualizada.', 'categoria' => $categoria]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('produtos.delete');

        $categoria = Categoria::findOrFail($id);

        if ($categoria->produtos()->exists()) {
            return response()->json(['message' => 'Categoria possui produtos vinculados.'], 422);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoria excluída.']);
    }
}
