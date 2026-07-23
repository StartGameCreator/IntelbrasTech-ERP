<?php

namespace App\Http\Controllers\Api\V1\Pdv;

use App\Http\Controllers\Controller;
use App\Models\{PdvNfceDocumento, PdvVenda};
use Illuminate\Http\{JsonResponse, Request};
use Barryvdh\DomPDF\Facade\Pdf;

class NfcePdvController extends Controller
{
    public function validar(int $id): JsonResponse
    {
        $this->authorize('notas_fiscais.create');

        $venda = PdvVenda::with(['pedido.itens','cliente'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        $empresa = auth()->user()->empresa;
        $erros = [];
        $avisos = [];

        if (!$empresa?->cnpj) $erros[] = 'CNPJ da empresa não informado.';
        if (!$empresa?->inscricao_estadual) $erros[] = 'Inscrição estadual não informada.';
        if (!$empresa?->uf) $erros[] = 'UF da empresa não informada.';
        if (!$venda->pedido?->itens?->count()) $erros[] = 'Venda sem itens.';

        foreach ($venda->pedido?->itens ?? [] as $i => $item) {
            if (!$item->ncm) $erros[] = 'Item '.($i + 1).': NCM não informado.';
            if (!$item->cfop) $erros[] = 'Item '.($i + 1).': CFOP não informado.';
            if (!$item->unidade) $erros[] = 'Item '.($i + 1).': unidade não informada.';
        }

        if (!$venda->cliente_id) $avisos[] = 'Consumidor final não identificado.';

        return response()->json([
            'valido' => !$erros,
            'erros' => $erros,
            'avisos' => $avisos,
        ]);
    }

    public function gerar(Request $request, int $id): JsonResponse
    {
        $this->authorize('notas_fiscais.create');

        $data = $request->validate([
            'ambiente' => 'required|in:homologacao,producao',
            'serie' => 'required|integer|min:1|max:999',
        ]);

        $venda = PdvVenda::with(['pedido.itens','cliente','pagamentos'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        $validacao = $this->validarInterno($venda);
        if ($validacao['erros']) {
            return response()->json([
                'message' => 'Existem pendências fiscais.',
                'validacoes' => $validacao,
            ], 422);
        }

        $empresa = auth()->user()->empresa;
        $numero = (int) (PdvNfceDocumento::where('empresa_id', $empresa->id)
            ->where('serie', $data['serie'])->max('numero') ?? 0) + 1;

        $xml = $this->xml($venda, $empresa, $data['ambiente'], $data['serie'], $numero);

        $documento = PdvNfceDocumento::updateOrCreate(
            ['empresa_id'=>$empresa->id,'pdv_venda_id'=>$venda->id],
            [
                'ambiente'=>$data['ambiente'],'serie'=>$data['serie'],
                'numero'=>$numero,'status'=>'xml_gerado',
                'xml_rascunho'=>$xml,'validacoes'=>$validacao,'gerado_em'=>now(),
            ]
        );

        $venda->update(['nfce_status'=>'xml_gerado']);

        return response()->json([
            'message'=>'XML de rascunho gerado.',
            'documento'=>$documento,
            'xml'=>$xml,
            'aviso'=>'XML ainda não assinado e não transmitido à SEFAZ.',
        ]);
    }

    public function pdf(int $id)
    {
        $this->authorize('notas_fiscais.view');

        $venda = PdvVenda::with([
            'pedido.itens',
            'cliente',
            'pagamentos',
            'caixa.deposito',
        ])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        $documento = PdvNfceDocumento::where('empresa_id', auth()->user()->empresa_id)
            ->where('pdv_venda_id', $venda->id)
            ->first();

        abort_unless($documento, 422, 'Gere a NFC-e antes de solicitar o PDF.');

        $empresa = auth()->user()->empresa;

        return Pdf::loadView('pdf.danfe-nfce-pdv', compact('venda', 'documento', 'empresa'))
            ->setPaper([0, 0, 226.77, 841.89])
            ->download("NFCe-{$venda->numero}.pdf");
    }

    public function documento(int $id): JsonResponse
    {
        $this->authorize('notas_fiscais.view');

        return response()->json([
            'data'=>PdvNfceDocumento::where('empresa_id', auth()->user()->empresa_id)
                ->where('pdv_venda_id', $id)->first(),
        ]);
    }

    private function validarInterno(PdvVenda $venda): array
    {
        $empresa = auth()->user()->empresa;
        $erros = [];
        $avisos = [];

        if (!$empresa?->cnpj) $erros[] = 'CNPJ da empresa não informado.';
        if (!$empresa?->inscricao_estadual) $erros[] = 'Inscrição estadual não informada.';
        if (!$empresa?->uf) $erros[] = 'UF da empresa não informada.';

        foreach ($venda->pedido?->itens ?? [] as $i => $item) {
            if (!$item->ncm) $erros[] = 'Item '.($i + 1).': NCM não informado.';
            if (!$item->cfop) $erros[] = 'Item '.($i + 1).': CFOP não informado.';
        }

        if (!$venda->cliente_id) $avisos[] = 'Consumidor final não identificado.';
        return compact('erros','avisos');
    }

    private function xml($venda, $empresa, string $ambiente, int $serie, int $numero): string
    {
        $e = fn($v) => htmlspecialchars((string)$v, ENT_XML1|ENT_QUOTES, 'UTF-8');
        $itens = '';

        foreach ($venda->pedido->itens as $i => $item) {
            $n = $i + 1;
            $itens .= '<det nItem="'.$n.'"><prod>'
                .'<cProd>'.$e($item->codigo).'</cProd>'
                .'<xProd>'.$e($item->descricao).'</xProd>'
                .'<NCM>'.$e($item->ncm).'</NCM>'
                .'<CFOP>'.$e($item->cfop).'</CFOP>'
                .'<uCom>'.$e($item->unidade).'</uCom>'
                .'<qCom>'.number_format((float)$item->quantidade,4,'.','').'</qCom>'
                .'<vUnCom>'.number_format((float)$item->preco_unitario,10,'.','').'</vUnCom>'
                .'<vProd>'.number_format((float)$item->total,2,'.','').'</vProd>'
                .'</prod></det>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="">'
            .'<ide><natOp>VENDA</natOp><mod>65</mod><serie>'.$serie.'</serie>'
            .'<nNF>'.$numero.'</nNF><dhEmi>'.now()->format('c').'</dhEmi>'
            .'<tpAmb>'.($ambiente === 'producao' ? '1' : '2').'</tpAmb>'
            .'<finNFe>1</finNFe><indFinal>1</indFinal><indPres>1</indPres></ide>'
            .'<emit><CNPJ>'.$e(preg_replace('/\D+/','',(string)$empresa->cnpj)).'</CNPJ>'
            .'<xNome>'.$e($empresa->razao_social).'</xNome>'
            .'<xFant>'.$e($empresa->nome_fantasia).'</xFant>'
            .'<IE>'.$e($empresa->inscricao_estadual).'</IE><CRT>1</CRT></emit>'
            .$itens
            .'<total><ICMSTot><vProd>'.number_format((float)$venda->subtotal,2,'.','').'</vProd>'
            .'<vDesc>'.number_format((float)$venda->desconto,2,'.','').'</vDesc>'
            .'<vNF>'.number_format((float)$venda->total,2,'.','').'</vNF>'
            .'</ICMSTot></total></infNFe></NFe>';
    }
}
