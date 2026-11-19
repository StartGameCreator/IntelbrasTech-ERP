<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;
use Illuminate\Http\{Request, JsonResponse};

class PipelineController extends Controller
{
    // ── GET /pipelines ─────────────────────────────────────────

    public function index(): JsonResponse
    {
        $this->authorize('crm.view');

        $pipelines = Pipeline::with('etapas')->where('ativo', true)->get();

        return response()->json($pipelines);
    }

    // ── POST /pipelines ────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('crm.create');

        $dados = $request->validate([
            'nome'          => 'required|string|max:100',
            'etapas'        => 'required|array|min:1',
            'etapas.*.nome' => 'required|string|max:100',
            'etapas.*.cor'  => 'nullable|string|max:7',
        ]);

        $pipeline = Pipeline::create(['empresa_id' => auth()->user()->empresa_id, 'nome' => $dados['nome']]);

        foreach ($dados['etapas'] as $i => $etapa) {
            $pipeline->etapas()->create(['nome' => $etapa['nome'], 'cor' => $etapa['cor'] ?? null, 'ordem' => $i]);
        }

        return response()->json(['message' => 'Pipeline criado com sucesso.', 'pipeline' => $pipeline->fresh('etapas')], 201);
    }

    // ── PUT /pipelines/{id} ────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('crm.edit');

        $pipeline = Pipeline::findOrFail($id);
        $pipeline->update($request->validate([
            'nome'  => 'sometimes|string|max:100',
            'ativo' => 'sometimes|boolean',
        ]));

        return response()->json(['message' => 'Pipeline atualizado.', 'pipeline' => $pipeline]);
    }

    // ── DELETE /pipelines/{id} ──────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('crm.delete');

        $pipeline = Pipeline::findOrFail($id);

        if ($pipeline->leads()->exists()) {
            return response()->json(['message' => 'Existem leads vinculados a este pipeline.'], 422);
        }

        $pipeline->etapas()->delete();
        $pipeline->delete();

        return response()->json(['message' => 'Pipeline excluído.']);
    }
}
