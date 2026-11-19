<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortalCarouselController extends Controller
{
    public function publico(): JsonResponse
    {
        $agora = now();
        $empresaId = config('portal.empresa_id') ?: DB::table('empresas')->where('ativo', true)->orderBy('id')->value('id');
        $items = DB::table('portal_carousel_items')
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('publicar_em')->orWhere('publicar_em', '<=', $agora))
            ->where(fn ($q) => $q->whereNull('expirar_em')->orWhere('expirar_em', '>=', $agora))
            ->orderBy('ordem')->orderBy('id')->get();

        return response()->json(['data' => $items]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('portal_carousel_items')
            ->where('empresa_id', $request->user()->empresa_id)
            ->orderBy('ordem')->orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validar($request);
        $id = DB::table('portal_carousel_items')->insertGetId(array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'empresa_id' => $request->user()->empresa_id,
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return response()->json(['message' => 'Item do carrossel criado.', 'data' => DB::table('portal_carousel_items')->find($id)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = $this->localizar($request, $id);
        DB::table('portal_carousel_items')->where('id', $item->id)->update(array_merge($this->validar($request), ['updated_at' => now()]));
        return response()->json(['message' => 'Item do carrossel atualizado.', 'data' => DB::table('portal_carousel_items')->find($id)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = $this->localizar($request, $id);
        DB::table('portal_carousel_items')->where('id', $item->id)->delete();
        return response()->json(['message' => 'Item removido do carrossel.']);
    }

    public function ordenar(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        foreach ($data['ids'] as $ordem => $id) {
            DB::table('portal_carousel_items')->where('empresa_id', $request->user()->empresa_id)->where('id', $id)
                ->update(['ordem' => $ordem + 1, 'updated_at' => now()]);
        }
        return response()->json(['message' => 'Ordem do carrossel atualizada.']);
    }

    private function localizar(Request $request, int $id): object
    {
        return DB::table('portal_carousel_items')->where('empresa_id', $request->user()->empresa_id)->where('id', $id)->firstOrFail();
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'tipo' => ['required', Rule::in(['produto', 'servico', 'campanha'])],
            'categoria' => 'nullable|string|max:120', 'titulo' => 'required|string|max:180',
            'descricao' => 'nullable|string|max:2000', 'imagem_url' => 'nullable|url|max:1000',
            'link_url' => 'nullable|string|max:1000', 'texto_link' => 'nullable|string|max:80',
            'link_externo' => 'required|boolean', 'ativo' => 'required|boolean',
            'ordem' => 'nullable|integer|min:0|max:9999', 'publicar_em' => 'nullable|date',
            'expirar_em' => 'nullable|date|after:publicar_em',
        ]);
    }
}
