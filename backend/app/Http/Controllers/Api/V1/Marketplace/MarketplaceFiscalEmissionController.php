<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\Fiscal\MarketplaceFiscalEmissionService;
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Support\Facades\DB;
use Throwable;

class MarketplaceFiscalEmissionController extends Controller
{
    public function __construct(
        private readonly MarketplaceFiscalEmissionService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $items = DB::table('marketplace_fiscal_emissions')
            ->where('empresa_id', $empresaId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'items' => $items,
            'resumo' => [
                'rascunhos' => DB::table('marketplace_fiscal_emissions')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'xml_gerado')
                    ->count(),
                'aguardando_assinatura' => DB::table('marketplace_fiscal_emissions')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'aguardando_assinatura')
                    ->count(),
                'autorizadas' => DB::table('marketplace_fiscal_emissions')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'autorizado')
                    ->count(),
                'erros' => DB::table('marketplace_fiscal_emissions')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'erro')
                    ->count(),
            ],
        ]);
    }

    public function validar(int $orderId): JsonResponse
    {
        return response()->json(
            $this->service->validate(auth()->user()->empresa_id, $orderId)
        );
    }

    public function gerar(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'documento_tipo' => 'required|in:nfe,nfce',
            'ambiente' => 'required|in:homologacao,producao',
            'serie' => 'required|integer|min:1|max:999',
        ]);

        try {
            $emission = $this->service->generate(
                auth()->user()->empresa_id,
                $orderId,
                $data['documento_tipo'],
                $data['ambiente'],
                (int) $data['serie']
            );

            return response()->json([
                'message' => 'XML fiscal de rascunho gerado.',
                'emissao' => $emission,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function enfileirar(int $id): JsonResponse
    {
        try {
            $this->service->markForTransmission(auth()->user()->empresa_id, $id);

            return response()->json([
                'message' => 'Emissão colocada na fila de assinatura.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function xml(int $id): Response
    {
        $row = DB::table('marketplace_fiscal_emissions')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->first();

        abort_unless($row, 404, 'Emissão não encontrada.');
        abort_unless($row->xml_rascunho, 404, 'XML não gerado.');

        return response(
            $row->xml_autorizado ?: $row->xml_assinado ?: $row->xml_rascunho,
            200,
            [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="emissao-' . $row->id . '.xml"',
            ]
        );
    }
}
