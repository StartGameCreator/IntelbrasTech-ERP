<?php

namespace App\Http\Controllers\Api\V1\OS;

use App\Http\Controllers\Controller;
use App\Models\OsStatus;
use Illuminate\Http\{Request, JsonResponse};

class OsStatusController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('os.view');

        return response()->json(OsStatus::orderBy('ordem')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('os.create');

        $dados = $request->validate([
            'nome'  => 'required|string|max:100',
            'cor'   => 'nullable|string|max:7',
            'ordem' => 'nullable|integer|min:0',
            'tipo'  => 'nullable|in:aberto,andamento,aguardando,concluido,entregue,cancelado',
        ]);

        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados['ordem'] = $dados['ordem'] ?? (OsStatus::where('empresa_id', $dados['empresa_id'])->max('ordem') + 1);

        $status = OsStatus::create($dados);

        return response()->json(['message' => 'Status criado.', 'status' => $status], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('os.edit');

        $status = OsStatus::findOrFail($id);
        $status->update($request->validate([
            'nome'  => 'sometimes|string|max:100',
            'cor'   => 'nullable|string|max:7',
            'ordem' => 'sometimes|integer|min:0',
            'tipo'  => 'sometimes|in:aberto,andamento,aguardando,concluido,entregue,cancelado',
        ]));

        return response()->json(['message' => 'Status atualizado.', 'status' => $status]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('os.delete');

        $status = OsStatus::findOrFail($id);

        if ($status->ordensServico()->exists()) {
            return response()->json(['message' => 'Existem ordens de serviço vinculadas a este status.'], 422);
        }

        $status->delete();

        return response()->json(['message' => 'Status excluído.']);
    }
}
