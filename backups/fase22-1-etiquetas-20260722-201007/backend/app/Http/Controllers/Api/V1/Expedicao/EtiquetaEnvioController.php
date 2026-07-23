<?php

namespace App\Http\Controllers\Api\V1\Expedicao;

use App\Http\Controllers\Controller;
use App\Models\EtiquetaEnvio;
use App\Models\Pedido;
use App\Services\Expedicao\EtiquetaEnvioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EtiquetaEnvioController extends Controller
{
    public function __construct(private readonly EtiquetaEnvioService $service) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $query = EtiquetaEnvio::query()
            ->with('pedido:id,numero,canal,marketplace,marketplace_pedido_id,codigo_rastreio')
            ->where('empresa_id', $empresaId)
            ->when($request->filled('expedicao_id'), fn ($q) => $q->where('expedicao_id', $request->integer('expedicao_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest();

        return response()->json($query->paginate(min((int) $request->get('per_page', 50), 100)));
    }

    public function upload(Request $request, int $expedicaoId): JsonResponse
    {
        $data = $request->validate([
            'arquivo' => ['required', 'file', 'max:10240', 'mimes:pdf,png,txt,zpl'],
            'marketplace' => ['nullable', 'string', 'max:40'],
            'transportadora' => ['nullable', 'string', 'max:80'],
            'servico' => ['nullable', 'string', 'max:100'],
            'external_shipment_id' => ['nullable', 'string', 'max:120'],
            'codigo_rastreio' => ['nullable', 'string', 'max:120'],
            'volume_id' => ['nullable', 'integer'],
            'tamanho_papel' => ['nullable', 'in:100x150,A4'],
        ]);

        $expedicao = $this->expedicao($expedicaoId);

        try {
            $etiqueta = $this->service->armazenarUpload(
                $expedicao,
                $request->file('arquivo'),
                $data,
                auth()->id()
            );

            return response()->json([
                'message' => 'Etiqueta oficial armazenada sem alteração.',
                'etiqueta' => $etiqueta,
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function gerar(Request $request, int $expedicaoId): JsonResponse
    {
        $data = $request->validate([
            'marketplace' => ['nullable', 'string', 'max:40'],
            'transportadora' => ['nullable', 'string', 'max:80'],
            'servico' => ['nullable', 'string', 'max:100'],
            'codigo_rastreio' => ['nullable', 'string', 'max:120'],
            'volume_id' => ['nullable', 'integer'],
            'volume_numero' => ['nullable', 'integer', 'min:1'],
            'total_volumes' => ['nullable', 'integer', 'min:1'],
            'destinatario_nome' => ['nullable', 'string', 'max:180'],
        ]);

        $expedicao = $this->expedicao($expedicaoId);
        $pedido = Pedido::with('cliente')
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($expedicao->pedido_id);

        try {
            $etiqueta = $this->service->gerarPadrao($expedicao, $pedido, $data, auth()->id());

            return response()->json([
                'message' => 'Etiqueta 10 × 15 cm gerada.',
                'etiqueta' => $etiqueta,
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function download(int $id): StreamedResponse
    {
        $etiqueta = $this->etiqueta($id);
        abort_unless(Storage::disk('local')->exists($etiqueta->arquivo_path), 404, 'Arquivo da etiqueta não encontrado.');

        return Storage::disk('local')->download(
            $etiqueta->arquivo_path,
            $etiqueta->nome_original ?: "etiqueta-{$etiqueta->id}.{$etiqueta->formato}",
            ['Content-Type' => $etiqueta->mime_type ?: 'application/octet-stream']
        );
    }

    public function imprimir(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'copias' => ['nullable', 'integer', 'min:1', 'max:20'],
            'impressora_id' => ['nullable', 'integer'],
            'modo' => ['nullable', 'in:navegador,agente_local,zpl'],
        ]);

        $etiqueta = $this->etiqueta($id);

        return response()->json([
            'message' => 'Impressão registrada.',
            'etiqueta' => $this->service->registrarImpressao($etiqueta, auth()->id(), $data),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $etiqueta = $this->etiqueta($id);

        if ($etiqueta->quantidade_impressoes > 0) {
            return response()->json([
                'message' => 'Etiqueta já impressa não pode ser excluída. Mantenha-a para auditoria.',
            ], 422);
        }

        Storage::disk('local')->delete($etiqueta->arquivo_path);
        $etiqueta->delete();

        return response()->json(['message' => 'Etiqueta removida.']);
    }

    private function expedicao(int $id): object
    {
        $expedicao = DB::table('expedicoes')
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('id', $id)
            ->first();

        abort_unless($expedicao, 404, 'Expedição não encontrada.');
        return $expedicao;
    }

    private function etiqueta(int $id): EtiquetaEnvio
    {
        return EtiquetaEnvio::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
    }
}
