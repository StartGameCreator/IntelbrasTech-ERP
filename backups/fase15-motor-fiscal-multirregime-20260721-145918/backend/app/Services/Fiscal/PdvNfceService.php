<?php

namespace App\Services\Fiscal;

use App\Models\{
    CertificadoDigital,
    ConfiguracaoFiscal,
    NfceCsc,
    NotaFiscal,
    NotaFiscalItem,
    PdvVenda,
    SerieFiscal
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class PdvNfceService
{
    public function __construct(
        private readonly NFeService $motorFiscal,
        private readonly RegimeTributarioService $regime
    ) {}

    public function validar(PdvVenda $venda): array
    {
        $venda->loadMissing(['pedido.itens.produto.unidade', 'cliente', 'pagamentos', 'notaFiscal', 'empresa']);
        $empresa = $venda->empresa ?: auth()->user()->empresa;
        $config = ConfiguracaoFiscal::where('empresa_id', $venda->empresa_id)->first();

        $erros = [];

        try {
            $this->regime->validarPerfil($empresa);
        } catch (RuntimeException $e) {
            $erros[] = $e->getMessage();
        }
        $avisos = [];

        if (!$empresa?->cnpj) $erros[] = 'CNPJ da empresa não informado.';
        if (!$empresa?->ie && !$empresa?->ie_isento) $erros[] = 'Inscrição estadual da empresa não informada.';
        if (strlen((string) $empresa?->estado) !== 2) $erros[] = 'UF da empresa não informada.';
        if (strlen(preg_replace('/\D+/', '', (string) $empresa?->ibge)) !== 7) $erros[] = 'Código IBGE da empresa não informado.';
        if (strlen(preg_replace('/\D+/', '', (string) $empresa?->cep)) !== 8) $erros[] = 'CEP da empresa não informado.';

        if (!$config || !$config->ativo) {
            $erros[] = 'Configuração fiscal da empresa não foi ativada.';
        } else {
            if (!$config->uf) $erros[] = 'UF não informada nas configurações fiscais.';
            if (!(int) $config->serie_nfce) $erros[] = 'Série da NFC-e não configurada.';
        }

        $ambiente = (int) ($config?->ambiente_nfce ?: 2);

        if (!NfceCsc::where('empresa_id', $venda->empresa_id)
            ->where('ambiente', $ambiente)
            ->where('ativo', true)
            ->exists()) {
            $erros[] = 'Nenhum CSC ativo foi encontrado para o ambiente da NFC-e.';
        }

        if (!CertificadoDigital::where('empresa_id', $venda->empresa_id)
            ->where('ativo', true)
            ->where(function ($q) {
                $q->whereNull('validade')->orWhereDate('validade', '>', now());
            })
            ->exists()) {
            $erros[] = 'Nenhum certificado digital A1 válido foi encontrado para a empresa.';
        }

        if (!$venda->pedido?->itens?->count()) {
            $erros[] = 'Venda sem itens.';
        }

        foreach ($venda->pedido?->itens ?? [] as $i => $item) {
            $produto = $item->produto;
            $ncm = preg_replace('/\D+/', '', (string) ($item->ncm ?: $produto?->ncm));
            $cfop = preg_replace('/\D+/', '', (string) ($item->cfop ?: $produto?->cfop ?: '5102'));
            $unidade = $item->unidade ?: $produto?->unidade?->sigla;

            if (strlen($ncm) !== 8) $erros[] = 'Item '.($i + 1).': NCM deve possuir 8 dígitos.';
            if (strlen($cfop) !== 4) $erros[] = 'Item '.($i + 1).': CFOP deve possuir 4 dígitos.';
            if (!$unidade) $erros[] = 'Item '.($i + 1).': unidade comercial não informada.';
            if ((float) $item->quantidade <= 0) $erros[] = 'Item '.($i + 1).': quantidade inválida.';
        }

        if (!$venda->cliente_id) {
            $avisos[] = 'Consumidor final não identificado. A NFC-e será preparada sem destinatário.';
        }

        return [
            'valido' => empty($erros),
            'erros' => $erros,
            'avisos' => $avisos,
            'configuracao' => $config ? [
                'ambiente' => $ambiente,
                'ambiente_nome' => $ambiente === 1 ? 'Produção' : 'Homologação',
                'serie' => (int) $config->serie_nfce,
                'uf' => $config->uf,
            ] : null,
            'nota_fiscal_id' => $venda->nota_fiscal_id,
            'status' => $venda->nfce_status ?: 'nao_emitida',
        ];
    }

    public function preparar(PdvVenda $venda): NotaFiscal
    {
        $validacao = $this->validar($venda);

        if (!$validacao['valido']) {
            throw new RuntimeException("Existem pendências fiscais:\n- ".implode("\n- ", $validacao['erros']));
        }

        return DB::transaction(function () use ($venda, $validacao) {
            $venda->loadMissing(['pedido.itens.produto.unidade', 'cliente', 'pagamentos', 'empresa']);
            $empresa = $venda->empresa ?: auth()->user()->empresa;
            $cliente = $venda->cliente;
            $config = ConfiguracaoFiscal::where('empresa_id', $venda->empresa_id)->firstOrFail();

            $nota = $venda->nota_fiscal_id
                ? NotaFiscal::where('empresa_id', $venda->empresa_id)->find($venda->nota_fiscal_id)
                : null;

            if ($nota && in_array($nota->status, [NotaFiscal::STATUS_AUTORIZADA, NotaFiscal::STATUS_CANCELADA], true)) {
                return $nota;
            }

            $serie = $this->obterSerie(
                $venda->empresa_id,
                (int) $config->serie_nfce,
                (int) $config->ambiente_nfce
            );

            if (!$nota) {
                $numero = $serie->proximo_numero;

                $nota = NotaFiscal::create([
                    'empresa_id' => $venda->empresa_id,
                    'pedido_id' => $venda->pedido_id,
                    'tipo' => 'NFCe',
                    'modelo' => '65',
                    'serie' => $serie->serie,
                    'numero' => $numero,
                    'natureza_operacao' => 'VENDA AO CONSUMIDOR',
                    'ambiente' => (int) $config->ambiente_nfce,
                    'finalidade' => 1,
                    'emitente_cnpj' => $empresa->cnpj,
                    'emitente_ie' => $empresa->ie,
                    'emitente_razao' => $empresa->razao_social,
                    'destinatario_cpf_cnpj' => $cliente?->cpf_cnpj,
                    'destinatario_ie' => null,
                    'destinatario_nome' => $cliente?->razao_social ?: $cliente?->nome_fantasia,
                    'destinatario_email' => $cliente?->email_nfe ?: $cliente?->email,
                    'destinatario_cep' => $cliente?->cep,
                    'destinatario_logradouro' => $cliente?->logradouro,
                    'destinatario_numero' => $cliente?->numero,
                    'destinatario_complemento' => $cliente?->complemento,
                    'destinatario_bairro' => $cliente?->bairro,
                    'destinatario_cidade' => $cliente?->cidade,
                    'destinatario_uf' => $cliente?->estado,
                    'destinatario_ibge' => $cliente?->ibge,
                    'valor_frete' => 0,
                    'valor_seguro' => 0,
                    'outras_despesas' => 0,
                    'valor_desconto' => 0,
                    'tipo_frete' => '9',
                    'informacoes_adicionais' => 'NFC-e originada da venda PDV '.$venda->numero,
                    'status' => NotaFiscal::STATUS_RASCUNHO,
                    'data_emissao' => now(),
                ]);

                $serie->update(['ultimo_numero' => $numero]);
            }

            // Sempre reconstrói os itens da nota ainda não autorizada.
            // Isso elimina valores antigos e garante totais consistentes.
            $nota->itens()->delete();
            $this->inserirItens($nota, $venda);
            $this->calcularTotais($nota);

            $venda->update([
                'nota_fiscal_id' => $nota->id,
                'nfce_status' => 'preparada',
            ]);

            $this->sincronizarDocumentoLegado($venda, $nota->fresh(), $validacao);

            return $nota->fresh(['itens']);
        });
    }

    public function emitir(PdvVenda $venda): NotaFiscal
    {
        $nota = $this->preparar($venda);

        if ($nota->status === NotaFiscal::STATUS_AUTORIZADA) {
            return $nota;
        }

        try {
            $venda->update(['nfce_status' => 'processando']);
            $nota = $this->motorFiscal->emitir($nota->fresh(['itens']));
            $venda->update(['nfce_status' => 'autorizada']);

            $this->sincronizarDocumentoLegado($venda, $nota, [
                'valido' => true,
                'erros' => [],
                'avisos' => [],
            ]);

            return $nota;
        } catch (Throwable $e) {
            $venda->update(['nfce_status' => 'rejeitada']);
            throw $e;
        }
    }

    private function obterSerie(int $empresaId, int $serieConfigurada, int $ambiente): SerieFiscal
    {
        $serie = (string) $serieConfigurada;

        return SerieFiscal::where('empresa_id', $empresaId)
            ->where('tipo', 'NFCe')
            ->where('serie', $serie)
            ->lockForUpdate()
            ->firstOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'tipo' => 'NFCe',
                    'serie' => $serie,
                ],
                [
                    'ultimo_numero' => 0,
                    'ambiente' => $ambiente,
                    'ativo' => true,
                ]
            );
    }

    private function inserirItens(NotaFiscal $nota, PdvVenda $venda): void
    {
        $simples = $this->regime->usaCsosn($venda->empresa);

        foreach ($venda->pedido->itens as $i => $item) {
            $produto = $item->produto;
            $quantidade = round((float) $item->quantidade, 4);
            $unitario = round((float) $item->preco_unitario, 10);
            $desconto = round((float) ($item->desconto_valor ?: 0), 2);

            // vProd deve ser quantidade x valor unitário, sem subtrair desconto.
            $valorBruto = round($quantidade * $unitario, 2);

            $icmsCst = $this->regime->normalizarIcms(
                $venda->empresa,
                $item->icms_cst ?: $produto?->icms_cst
            );
            $pisCst = $this->regime->normalizarPis(
                $venda->empresa,
                $item->pis_cst ?: $produto?->pis_cst
            );
            $cofinsCst = $this->regime->normalizarCofins(
                $venda->empresa,
                $item->cofins_cst ?: $produto?->cofins_cst
            );

            $baseTributavel = max(0, $valorBruto - $desconto);
            $icmsBase = (!$simples && in_array($icmsCst, ['00', '000'], true)) ? $baseTributavel : 0;
            $pisBase = $pisCst === '01' ? $baseTributavel : 0;
            $cofinsBase = $cofinsCst === '01' ? $baseTributavel : 0;

            $icmsAliquota = (float) ($item->icms_aliquota ?: $produto?->icms_aliquota ?: 0);
            $pisAliquota = (float) ($item->pis_aliquota ?: $produto?->pis_aliquota ?: 0);
            $cofinsAliquota = (float) ($item->cofins_aliquota ?: $produto?->cofins_aliquota ?: 0);

            NotaFiscalItem::create([
                'nota_fiscal_id' => $nota->id,
                'produto_id' => $produto?->id,
                'numero_item' => $i + 1,
                'codigo' => $item->codigo ?: $produto?->codigo ?: $produto?->sku,
                'descricao' => $item->descricao ?: $produto?->nome,
                'ncm' => $item->ncm ?: $produto?->ncm,
                'cfop' => $item->cfop ?: $produto?->cfop ?: '5102',
                'unidade' => $item->unidade ?: $produto?->unidade?->sigla ?: 'UN',
                'quantidade' => $quantidade,
                'valor_unitario' => $unitario,
                'valor_total' => $valorBruto,
                'valor_desconto' => $desconto,
                'icms_origem' => $produto?->origem ?: 0,
                'icms_cst' => $icmsCst,
                'icms_base' => $icmsBase,
                'icms_aliquota' => $icmsAliquota,
                'icms_valor' => round($icmsBase * $icmsAliquota / 100, 2),
                'pis_cst' => $pisCst,
                'pis_base' => $pisBase,
                'pis_aliquota' => $pisAliquota,
                'pis_valor' => round($pisBase * $pisAliquota / 100, 2),
                'cofins_cst' => $cofinsCst,
                'cofins_base' => $cofinsBase,
                'cofins_aliquota' => $cofinsAliquota,
                'cofins_valor' => round($cofinsBase * $cofinsAliquota / 100, 2),
                'ipi_cst' => $produto?->ipi_cst,
                'ipi_aliquota' => $produto?->ipi_aliquota ?: 0,
                'ipi_base' => 0,
                'ipi_valor' => 0,
            ]);
        }
    }

    private function calcularTotais(NotaFiscal $nota): void
    {
        $itens = $nota->itens()->get();

        $valorProdutos = round((float) $itens->sum('valor_total'), 2);
        $valorDesconto = round((float) $itens->sum('valor_desconto'), 2);
        $valorFrete = round((float) ($nota->valor_frete ?: 0), 2);
        $valorSeguro = round((float) ($nota->valor_seguro ?: 0), 2);
        $outrasDespesas = round((float) ($nota->outras_despesas ?: 0), 2);
        $valorIpi = round((float) $itens->sum('ipi_valor'), 2);

        // Regra do vNF:
        // vProd - vDesc + vST + vFCPST + vFrete + vSeg + vOutro + vII + vIPI + vIPIDevol
        // Neste fluxo, campos não utilizados são zero.
        $valorTotal = round(
            $valorProdutos
            - $valorDesconto
            + $valorFrete
            + $valorSeguro
            + $outrasDespesas
            + $valorIpi,
            2
        );

        $nota->update([
            'valor_produtos' => $valorProdutos,
            'valor_desconto' => $valorDesconto,
            'valor_frete' => $valorFrete,
            'valor_seguro' => $valorSeguro,
            'outras_despesas' => $outrasDespesas,
            'base_calculo_icms' => round((float) $itens->sum('icms_base'), 2),
            'valor_icms' => round((float) $itens->sum('icms_valor'), 2),
            'valor_ipi' => $valorIpi,
            'base_pis' => round((float) $itens->sum('pis_base'), 2),
            'valor_pis' => round((float) $itens->sum('pis_valor'), 2),
            'base_cofins' => round((float) $itens->sum('cofins_base'), 2),
            'valor_cofins' => round((float) $itens->sum('cofins_valor'), 2),
            'valor_total' => $valorTotal,
        ]);
    }

    private function sincronizarDocumentoLegado(PdvVenda $venda, NotaFiscal $nota, array $validacao): void
    {
        if (!Schema::hasTable('pdv_nfce_documentos')) {
            return;
        }

        DB::table('pdv_nfce_documentos')->updateOrInsert(
            [
                'empresa_id' => $venda->empresa_id,
                'pdv_venda_id' => $venda->id,
            ],
            [
                'ambiente' => (int) $nota->ambiente === 1 ? 'producao' : 'homologacao',
                'serie' => $nota->serie,
                'numero' => $nota->numero,
                'status' => $nota->status,
                'xml_rascunho' => $nota->xml,
                'validacoes' => json_encode($validacao, JSON_UNESCAPED_UNICODE),
                'gerado_em' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
