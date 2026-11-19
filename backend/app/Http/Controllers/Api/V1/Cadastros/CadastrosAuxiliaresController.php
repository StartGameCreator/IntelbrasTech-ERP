<?php

namespace App\Http\Controllers\Api\V1\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\TabelaPreco;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CadastrosAuxiliaresController extends Controller
{
    public function usuarios(Request $request): JsonResponse
    {
        $query = Usuario::query()
            ->with('perfil:id,nome')
            ->where('empresa_id', auth()->user()->empresa_id)
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = trim((string) $request->busca);
                $q->where(function ($sub) use ($busca) {
                    $sub->where('nome', 'like', "%{$busca}%")
                        ->orWhere('email', 'like', "%{$busca}%")
                        ->orWhere('cargo', 'like', "%{$busca}%");
                });
            })
            ->when($request->has('ativo'), fn ($q) => $q->where('ativo', $request->boolean('ativo')))
            ->orderBy('nome');

        $perPage = min(max((int) $request->get('per_page', 50), 1), 200);

        return response()->json($query->paginate($perPage));
    }

    public function tabelasPreco(Request $request): JsonResponse
    {
        $query = TabelaPreco::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->when($request->filled('busca'), function ($q) use ($request) {
                $busca = trim((string) $request->busca);
                $q->where(function ($sub) use ($busca) {
                    $sub->where('nome', 'like', "%{$busca}%")
                        ->orWhere('descricao', 'like', "%{$busca}%");
                });
            })
            ->when($request->has('ativa'), fn ($q) => $q->where('ativa', $request->boolean('ativa')))
            ->orderBy('nome');

        $perPage = min(max((int) $request->get('per_page', 50), 1), 200);

        return response()->json($query->paginate($perPage));
    }
}
