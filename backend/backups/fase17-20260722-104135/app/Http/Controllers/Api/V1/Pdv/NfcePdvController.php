<?php

namespace App\Http\Controllers\Api\V1\Pdv;

use App\Exceptions\Fiscal\DataEmissaoNfceDesatualizadaException;
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
        $danfeLogoDataUri = $this->danfeLogoDataUri($empresa);

        return Pdf::loadView(
            'pdf.danfe-nfce-pdv',
            compact('venda', 'documento', 'empresa', 'danfeLogoDataUri')
        )
            ->setPaper([0, 0, 226.77, 841.89])
            ->download("NFCe-{$venda->numero}.pdf");
    }


    /**
     * Converte a logo cadastrada para JPEG em memória.
     *
     * O Dompdf apresenta falha ao interpretar determinados PNGs, inclusive
     * retornando uma mensagem incorreta sobre ausência da extensão GD.
     * Quando a imagem não puder ser validada, o DANFE continua sem a logo.
     */
    private function danfeLogoDataUri(?object $empresa): ?string
    {
        $logo = trim((string) ($empresa?->logo ?? ''));

        if ($logo === '') {
            return null;
        }

        $logoSemQuery = preg_replace('/[?#].*$/', '', $logo) ?: $logo;
        $logoRelativa = ltrim(str_replace('\\', '/', $logoSemQuery), '/');
        $logoStorage = preg_replace('#^storage/#i', '', $logoRelativa) ?: $logoRelativa;

        $candidatos = array_values(array_unique(array_filter([
            is_file($logoSemQuery) ? $logoSemQuery : null,
            public_path($logoRelativa),
            public_path('storage/'.$logoStorage),
            storage_path('app/public/'.$logoStorage),
        ])));

        $arquivo = null;

        foreach ($candidatos as $candidato) {
            if (is_file($candidato) && is_readable($candidato)) {
                $arquivo = $candidato;
                break;
            }
        }

        if (! $arquivo) {
            logger()->warning('Logo do DANFE não encontrada; o PDF será gerado sem logo.', [
                'empresa_id' => $empresa?->id,
                'logo' => $logo,
            ]);

            return null;
        }

        $conteudo = @file_get_contents($arquivo);

        if ($conteudo === false || $conteudo === '') {
            return null;
        }

        $origem = @\imagecreatefromstring($conteudo);

        if ($origem === false) {
            logger()->warning('Logo do DANFE inválida; o PDF será gerado sem logo.', [
                'empresa_id' => $empresa?->id,
                'arquivo' => $arquivo,
            ]);

            return null;
        }

        $largura = \imagesx($origem);
        $altura = \imagesy($origem);

        if ($largura < 1 || $altura < 1) {
            \imagedestroy($origem);

            return null;
        }

        $destino = \imagecreatetruecolor($largura, $altura);
        $branco = \imagecolorallocate($destino, 255, 255, 255);
        \imagefill($destino, 0, 0, $branco);
        \imagealphablending($destino, true);
        \imagecopy($destino, $origem, 0, 0, 0, 0, $largura, $altura);

        ob_start();
        $gravou = \imagejpeg($destino, null, 92);
        $jpeg = ob_get_clean();

        \imagedestroy($origem);
        \imagedestroy($destino);

        if (! $gravou || ! is_string($jpeg) || $jpeg === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpeg);
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
