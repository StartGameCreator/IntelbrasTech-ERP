<?php

namespace App\Http\Controllers\Api\V1\Pdv;

use App\Exceptions\Fiscal\DataEmissaoNfceDesatualizadaException;
use App\Http\Controllers\Controller;
use App\Models\PdvVenda;
use App\Services\Fiscal\PdvNfceService;
use App\Services\Fiscal\DanfeLogoService;
use App\Services\Fiscal\DanfeQrCodeService;
use App\Services\Fiscal\DanfeBarcodeService;
use App\Services\Fiscal\DanfeTaxTransparencyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NfcePdvController extends Controller
{
    public function __construct(
        private readonly PdvNfceService $nfce,
        private readonly DanfeLogoService $danfeLogo,
        private readonly DanfeQrCodeService $danfeQrCode,
        private readonly DanfeBarcodeService $danfeBarcode,
        private readonly DanfeTaxTransparencyService $danfeTaxTransparency
    ) {}

    public function validar(int $id): JsonResponse
    {
        $venda = $this->venda($id);

        try {
            return response()->json($this->nfce->validar($venda));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
                'valido' => false,
                'erros' => [$e->getMessage()],
                'avisos' => [],
                'configuracao' => null,
                'status' => $venda->nfce_status ?: 'nao_emitida',
            ], 422);
        }
    }

    public function gerar(Request $request, int $id): JsonResponse
    {
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

    public function emitir(Request $request, int $id): JsonResponse
    {
        $venda = $this->venda($id);

        try {
            $nota = $this->nfce->emitir($venda, $request->boolean('confirmar_atualizacao_data'));

            return response()->json([
                'message' => $nota->status === 'autorizada'
                    ? 'NFC-e autorizada pela SEFAZ.'
                    : 'NFC-e processada pelo motor fiscal.',
                'documento' => $this->documentoPayload($venda->fresh(), $nota),
                'nota_fiscal' => $nota,
            ]);
        } catch (DataEmissaoNfceDesatualizadaException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NFCE_DATA_EMISSAO_DESATUALIZADA',
                'requires_confirmation' => true,
                'data_venda' => $e->dataVenda,
                'data_emissao_fiscal' => $e->dataEmissaoFiscal,
                'data_atual' => $e->dataAtual,
            ], 409);
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
        $venda = $this->venda($id);
        $nota = $venda->notaFiscal;

        return response()->json([
            'data' => $nota ? $this->documentoPayload($venda, $nota) : null,
        ]);
    }

    public function pdf(int $id)
    {
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
        $danfeLogoDataUri = $this->danfeLogo->dataUri($empresa);
        $danfeQrCodeDataUri = $this->danfeQrCode->dataUri($venda->notaFiscal);
        $danfeBarcodeDataUri = $this->danfeBarcode->dataUri($venda->notaFiscal->chave_acesso);
        $danfeTributos = $this->danfeTaxTransparency->calculate($venda->notaFiscal);

        return Pdf::loadView(
            'pdf.danfe-nfce-pdv',
            compact('venda', 'documento', 'empresa', 'danfeLogoDataUri', 'danfeQrCodeDataUri', 'danfeBarcodeDataUri', 'danfeTributos')
        )
            ->setPaper('a4', 'portrait')
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
