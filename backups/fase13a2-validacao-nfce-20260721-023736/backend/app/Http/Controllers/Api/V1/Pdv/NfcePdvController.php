<?php

namespace App\Http\Controllers\Api\V1\Pdv;

use App\Http\Controllers\Controller;
use App\Models\PdvVenda;
use App\Services\Fiscal\PdvNfceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NfcePdvController extends Controller
{
    public function __construct(
        private readonly PdvNfceService $nfce
    ) {}

    public function validar(int $id): JsonResponse
    {
        $this->authorize('fiscal.view');
        return response()->json($this->nfce->validar($this->venda($id)));
    }

    public function gerar(Request $request, int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');
        $venda = $this->venda($id);

        try {
            $nota = $this->nfce->preparar($venda);

            return response()->json([
                'message' => 'NFC-e preparada no módulo fiscal.',
                'documento' => $this->documentoPayload($venda->fresh(), $nota),
                'nota_fiscal' => $nota,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
                'validacoes' => $this->nfce->validar($venda),
            ], 422);
        }
    }

    public function emitir(int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');
        $venda = $this->venda($id);

        try {
            $nota = $this->nfce->emitir($venda);

            return response()->json([
                'message' => $nota->status === 'autorizada'
                    ? 'NFC-e autorizada pela SEFAZ.'
                    : 'NFC-e processada pelo motor fiscal.',
                'documento' => $this->documentoPayload($venda->fresh(), $nota),
                'nota_fiscal' => $nota,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
                'validacoes' => $this->nfce->validar($venda),
            ], 422);
        }
    }

    public function documento(int $id): JsonResponse
    {
        $this->authorize('fiscal.view');
        $venda = $this->venda($id);
        $nota = $venda->notaFiscal;

        return response()->json([
            'data' => $nota ? $this->documentoPayload($venda, $nota) : null,
        ]);
    }

    public function pdf(int $id)
    {
        $this->authorize('fiscal.view');

        $venda = $this->venda($id)->load([
            'pedido.itens',
            'cliente',
            'pagamentos',
            'caixa.deposito',
            'notaFiscal',
        ]);

        abort_unless($venda->notaFiscal, 422, 'Prepare a NFC-e antes de solicitar o PDF.');

        $documento = (object) [
            'ambiente' => (int) $venda->notaFiscal->ambiente === 1 ? 'producao' : 'homologacao',
            'serie' => $venda->notaFiscal->serie,
            'numero' => $venda->notaFiscal->numero,
            'status' => $venda->notaFiscal->status,
            'chave_acesso' => $venda->notaFiscal->chave_acesso,
            'protocolo' => $venda->notaFiscal->protocolo,
        ];

        $empresa = auth()->user()->empresa;

        return Pdf::loadView('pdf.danfe-nfce-pdv', compact('venda', 'documento', 'empresa'))
            ->setPaper([0, 0, 226.77, 841.89])
            ->download("NFCe-{$venda->numero}.pdf");
    }

    private function venda(int $id): PdvVenda
    {
        return PdvVenda::with([
            'empresa',
            'pedido.itens.produto.unidade',
            'cliente',
            'pagamentos',
            'notaFiscal',
        ])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    private function documentoPayload(PdvVenda $venda, $nota): array
    {
        return [
            'id' => $nota->id,
            'pdv_venda_id' => $venda->id,
            'nota_fiscal_id' => $nota->id,
            'status' => $nota->status,
            'ambiente' => (int) $nota->ambiente === 1 ? 'producao' : 'homologacao',
            'ambiente_nome' => (int) $nota->ambiente === 1 ? 'Produção' : 'Homologação',
            'serie' => $nota->serie,
            'numero' => $nota->numero,
            'chave_acesso' => $nota->chave_acesso,
            'protocolo' => $nota->protocolo,
            'informacoes_fisco' => $nota->informacoes_fisco,
            'danfe_url' => $nota->danfe_url,
        ];
    }
}
