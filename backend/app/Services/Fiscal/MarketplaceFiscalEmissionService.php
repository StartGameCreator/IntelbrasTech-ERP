<?php

namespace App\Services\Fiscal;

use DOMDocument;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketplaceFiscalEmissionService
{
    public function validate(int $empresaId, int $marketplaceOrderId): array
    {
        $errors = [];
        $warnings = [];

        $order = DB::table('marketplace_orders')
            ->where('id', $marketplaceOrderId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$order) {
            $errors[] = 'Pedido do marketplace não encontrado.';
            return compact('errors', 'warnings');
        }

        if (!$order->pedido_id) {
            $errors[] = 'O pedido ainda não foi convertido em pedido interno.';
        }

        $company = DB::table('empresas')->where('id', $empresaId)->first();

        if (!$company?->cnpj) $errors[] = 'CNPJ da empresa não informado.';
        if (!$company?->inscricao_estadual) $errors[] = 'Inscrição estadual não informada.';
        if (!$company?->uf) $errors[] = 'UF da empresa não informada.';
        if (!$company?->razao_social) $errors[] = 'Razão social da empresa não informada.';

        $items = $order->pedido_id
            ? DB::table('pedido_itens')->where('pedido_id', $order->pedido_id)->get()
            : collect();

        if ($items->isEmpty()) {
            $errors[] = 'Pedido interno sem itens.';
        }

        foreach ($items as $index => $item) {
            if (!$item->ncm) $errors[] = 'Item ' . ($index + 1) . ': NCM não informado.';
            if (!$item->cfop) $errors[] = 'Item ' . ($index + 1) . ': CFOP não informado.';
            if (!$item->unidade) $errors[] = 'Item ' . ($index + 1) . ': unidade não informada.';
            if ((float) $item->quantidade <= 0) $errors[] = 'Item ' . ($index + 1) . ': quantidade inválida.';
        }

        $certificate = DB::table('certificados_digitais')
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (!$certificate) {
            $warnings[] = 'Nenhum certificado digital ativo foi encontrado. O XML poderá ser gerado, mas não assinado.';
        }

        return [
            'valido' => !$errors,
            'erros' => $errors,
            'avisos' => $warnings,
        ];
    }

    public function generate(
        int $empresaId,
        int $marketplaceOrderId,
        string $documentType,
        string $environment,
        int $series
    ): object {
        $validation = $this->validate($empresaId, $marketplaceOrderId);

        if (!$validation['valido']) {
            throw new RuntimeException(implode(' | ', $validation['erros']));
        }

        $order = DB::table('marketplace_orders')
            ->where('id', $marketplaceOrderId)
            ->where('empresa_id', $empresaId)
            ->firstOrFail();

        $company = DB::table('empresas')->where('id', $empresaId)->firstOrFail();
        $items = DB::table('pedido_itens')->where('pedido_id', $order->pedido_id)->get();

        $number = (int) (
            DB::table('marketplace_fiscal_emissions')
                ->where('empresa_id', $empresaId)
                ->where('documento_tipo', $documentType)
                ->where('serie', $series)
                ->max('numero') ?? 0
        ) + 1;

        $xml = $this->buildXml(
            $company,
            $order,
            $items,
            $documentType,
            $environment,
            $series,
            $number
        );

        $queue = DB::table('marketplace_fiscal_queue_items')
            ->where('empresa_id', $empresaId)
            ->where('marketplace_order_id', $marketplaceOrderId)
            ->first();

        DB::table('marketplace_fiscal_emissions')->updateOrInsert(
            [
                'empresa_id' => $empresaId,
                'marketplace_order_id' => $marketplaceOrderId,
            ],
            [
                'pedido_id' => $order->pedido_id,
                'fiscal_queue_item_id' => $queue?->id,
                'documento_tipo' => $documentType,
                'ambiente' => $environment,
                'serie' => $series,
                'numero' => $number,
                'status' => 'xml_gerado',
                'xml_rascunho' => $xml,
                'validacoes' => json_encode($validation, JSON_UNESCAPED_UNICODE),
                'gerado_em' => now(),
                'mensagem_status' => 'XML de rascunho gerado. Assinatura e transmissão pendentes.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return DB::table('marketplace_fiscal_emissions')
            ->where('empresa_id', $empresaId)
            ->where('marketplace_order_id', $marketplaceOrderId)
            ->first();
    }

    public function markForTransmission(int $empresaId, int $emissionId): void
    {
        $emission = DB::table('marketplace_fiscal_emissions')
            ->where('id', $emissionId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$emission) {
            throw new RuntimeException('Emissão fiscal não encontrada.');
        }

        if (!$emission->xml_rascunho) {
            throw new RuntimeException('Gere o XML antes de colocar a emissão na fila.');
        }

        DB::table('marketplace_fiscal_emissions')
            ->where('id', $emissionId)
            ->update([
                'status' => 'aguardando_assinatura',
                'mensagem_status' => 'Aguardando certificado e assinatura digital.',
                'tentativas' => DB::raw('tentativas + 1'),
                'updated_at' => now(),
            ]);
    }

    private function buildXml(
        object $company,
        object $order,
        $items,
        string $documentType,
        string $environment,
        int $series,
        int $number
    ): string {
        $model = $documentType === 'nfce' ? '65' : '55';
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $nfe = $dom->createElementNS('http://www.portalfiscal.inf.br/nfe', 'NFe');
        $dom->appendChild($nfe);

        $info = $dom->createElement('infNFe');
        $info->setAttribute('Id', '');
        $info->setAttribute('versao', '4.00');
        $nfe->appendChild($info);

        $ide = $dom->createElement('ide');
        $info->appendChild($ide);
        $this->append($dom, $ide, 'natOp', 'VENDA');
        $this->append($dom, $ide, 'mod', $model);
        $this->append($dom, $ide, 'serie', (string) $series);
        $this->append($dom, $ide, 'nNF', (string) $number);
        $this->append($dom, $ide, 'dhEmi', now()->format('c'));
        $this->append($dom, $ide, 'tpNF', '1');
        $this->append($dom, $ide, 'idDest', '1');
        $this->append($dom, $ide, 'tpAmb', $environment === 'producao' ? '1' : '2');
        $this->append($dom, $ide, 'finNFe', '1');
        $this->append($dom, $ide, 'indFinal', '1');
        $this->append($dom, $ide, 'indPres', '2');

        $emit = $dom->createElement('emit');
        $info->appendChild($emit);
        $this->append($dom, $emit, 'CNPJ', preg_replace('/\D+/', '', (string) $company->cnpj));
        $this->append($dom, $emit, 'xNome', (string) $company->razao_social);
        $this->append($dom, $emit, 'xFant', (string) ($company->nome_fantasia ?? ''));
        $this->append($dom, $emit, 'IE', preg_replace('/\D+/', '', (string) $company->inscricao_estadual));
        $this->append($dom, $emit, 'CRT', '1');

        foreach ($items as $index => $item) {
            $det = $dom->createElement('det');
            $det->setAttribute('nItem', (string) ($index + 1));
            $info->appendChild($det);

            $product = $dom->createElement('prod');
            $det->appendChild($product);

            $this->append($dom, $product, 'cProd', (string) $item->codigo);
            $this->append($dom, $product, 'xProd', (string) $item->descricao);
            $this->append($dom, $product, 'NCM', (string) $item->ncm);
            $this->append($dom, $product, 'CFOP', (string) $item->cfop);
            $this->append($dom, $product, 'uCom', (string) $item->unidade);
            $this->append($dom, $product, 'qCom', number_format((float) $item->quantidade, 4, '.', ''));
            $this->append($dom, $product, 'vUnCom', number_format((float) $item->preco_unitario, 10, '.', ''));
            $this->append($dom, $product, 'vProd', number_format((float) $item->total, 2, '.', ''));
        }

        $total = $dom->createElement('total');
        $info->appendChild($total);
        $icmsTotal = $dom->createElement('ICMSTot');
        $total->appendChild($icmsTotal);
        $this->append($dom, $icmsTotal, 'vProd', number_format((float) $order->total, 2, '.', ''));
        $this->append($dom, $icmsTotal, 'vNF', number_format((float) $order->total, 2, '.', ''));

        return $dom->saveXML();
    }

    private function append(DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $parent->appendChild($dom->createElement($name, htmlspecialchars($value, ENT_XML1)));
    }
}
