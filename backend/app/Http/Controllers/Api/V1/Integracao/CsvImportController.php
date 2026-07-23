<?php

namespace App\Http\Controllers\Api\V1\Integracao;

use App\Http\Controllers\Controller;
use App\Models\ImportacaoCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CsvImportController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('integracoes.view');

        return response()->json([
            'data' => ImportacaoCsv::orderByDesc('created_at')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('integracoes.edit');

        $data = $request->validate([
            'tipo' => ['required', Rule::in(['clientes', 'produtos', 'estoque', 'pedidos', 'fornecedores', 'marketplace'])],
            'arquivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt'],
            'mapeamento' => ['nullable', 'array'],
        ]);

        $path = $request->file('arquivo')->store('importacoes-csv', 'local');
        $conteudo = Storage::disk('local')->get($path);
        $linhas = array_filter(preg_split('/\r\n|\r|\n/', $conteudo));

        $importacao = ImportacaoCsv::create([
            'empresa_id' => auth()->user()->empresa_id,
            'tipo' => $data['tipo'],
            'nome_original' => $request->file('arquivo')->getClientOriginalName(),
            'arquivo' => $path,
            'status' => 'recebido',
            'total_linhas' => max(count($linhas) - 1, 0),
            'linhas_processadas' => 0,
            'erros' => [],
            'mapeamento' => $data['mapeamento'] ?? [],
        ]);

        return response()->json([
            'message' => 'CSV cadastrado com sucesso. O processamento poderá ser executado na próxima etapa.',
            'data' => $importacao,
        ], 201);
    }
}
