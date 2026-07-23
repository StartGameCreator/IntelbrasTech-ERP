<?php

namespace App\Services\Fiscal;

use App\Models\{NotaFiscal, NotaFiscalItem, Pedido, Empresa, CertificadoDigital, SerieFiscal};
use App\Events\NFeAutorizadaEvent;
use Illuminate\Support\Facades\{DB, Log, Storage};
use Exception;

class NFeService
{
    public function __construct(
        private readonly SefazService $sefaz,
        private readonly DanfeService $danfe,
        private readonly ValidacaoFiscalService $validacao,
        private readonly ChaveAcessoService $chaveAcesso,
    ) {}

    // ──────────────────────────────────────────────────────────
    // MONTAR NF-e a partir de um Pedido
    // ──────────────────────────────────────────────────────────

    public function montarDePedido(Pedido $pedido): NotaFiscal
    {
        DB::beginTransaction();
        try {
            $empresa   = $pedido->empresa;
            $cliente   = $pedido->cliente;
            $serie     = $this->obterSerieBloqueada($empresa, 'NFe');
            $numero    = $serie->proximo_numero;
            $serie->update(['ultimo_numero' => $numero]);

            $nfe = NotaFiscal::create([
                'empresa_id'        => $empresa->id,
                'pedido_id'         => $pedido->id,
                'tipo'              => 'NFe',
                'modelo'            => '55',
                'serie'             => $serie->serie,
                'numero'            => $numero,
                'natureza_operacao' => 'VENDA DE MERCADORIA',
                'ambiente'          => $serie->ambiente,
                'finalidade'        => 1,

                // Emitente
                'emitente_cnpj'    => $empresa->cnpj,
                'emitente_ie'      => $empresa->ie,
                'emitente_razao'   => $empresa->razao_social,

                // Destinatário
                'destinatario_cpf_cnpj'    => $cliente->cpf_cnpj,
                'destinatario_nome'        => $cliente->razao_social,
                'destinatario_email'       => $cliente->email_nfe ?? $cliente->email,
                'destinatario_cep'         => $pedido->entrega_cep ?? $cliente->cep,
                'destinatario_logradouro'  => $pedido->entrega_logradouro ?? $cliente->logradouro,
                'destinatario_numero'      => $pedido->entrega_numero ?? $cliente->numero,
                'destinatario_complemento' => $pedido->entrega_complemento ?? $cliente->complemento,
                'destinatario_bairro'      => $pedido->entrega_bairro ?? $cliente->bairro,
                'destinatario_cidade'      => $pedido->entrega_cidade ?? $cliente->cidade,
                'destinatario_uf'          => $pedido->entrega_estado ?? $cliente->estado,
                'destinatario_ibge'        => $pedido->entrega_ibge ?? $cliente->ibge,

                'valor_frete'    => $pedido->frete,
                'valor_desconto' => $pedido->desconto_valor,
                'tipo_frete'      => '9',
                'informacoes_adicionais' => $pedido->observacoes_nfe,
                'status'         => NotaFiscal::STATUS_RASCUNHO,
                'data_emissao'   => now(),
            ]);

            // Inserir itens
            $this->inserirItens($nfe, $pedido);

            // Calcular totais
            $this->calcularTotais($nfe);

            DB::commit();
            return $nfe->fresh(['itens']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("NFeService::montarDePedido — {$e->getMessage()}", [
                'pedido_id' => $pedido->id,
                'trace'     => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // EMITIR — Assinar + Enviar SEFAZ
    // ──────────────────────────────────────────────────────────

    public function emitir(NotaFiscal $nfe): NotaFiscal
    {
        if (! in_array($nfe->status, [NotaFiscal::STATUS_RASCUNHO, 'rejeitada'])) {
            throw new Exception("NF-e não pode ser emitida no status atual: {$nfe->status}");
        }

        $nfe->update(['status' => NotaFiscal::STATUS_PROCESSANDO]);

        DB::beginTransaction();
        try {
            $empresa     = $nfe->empresa;
            $this->validacao->validarParaEmissao($nfe);
            $this->sefaz->assertDisponivel();
            $certificado = $this->obterCertificado($empresa);

            if (! $nfe->chave_acesso) {
                $nfe->update(['chave_acesso' => $this->chaveAcesso->gerar($nfe)]);
            }

            // 1. Gerar XML
            $xml = $this->gerarXml($nfe->load('itens'));

            // 2. Assinar XML
            $xmlAssinado = $this->sefaz->assinarXml($xml, $certificado);

            // 3. Enviar ao SEFAZ
            $retorno = $this->sefaz->enviarNFe($xmlAssinado, $nfe->ambiente, $nfe->emitente_uf ?? $empresa->estado);

            if ($retorno['cStat'] === '100') {
                // Autorizada
                $nfe->update([
                    'status'           => NotaFiscal::STATUS_AUTORIZADA,
                    'chave_acesso'     => $retorno['chNFe'] ?: $nfe->chave_acesso,
                    'protocolo'        => $retorno['nProt'],
                    'xml'              => $retorno['xml'] ?? $this->montarXmlProcessado($xmlAssinado, $retorno),
                    'data_autorizacao' => now(),
                ]);

                // A autorização fiscal é irreversível e deve ser confirmada no banco
                // antes de tarefas auxiliares como DANFE e notificações.
                DB::commit();

                try {
                    $danfeUrl = $this->danfe->gerar($nfe->fresh());
                    $nfe->update(['danfe_url' => $danfeUrl]);
                } catch (\Throwable $danfeError) {
                    Log::error('NF-e autorizada, mas o DANFE não foi gerado.', [
                        'nfe_id' => $nfe->id,
                        'erro' => $danfeError->getMessage(),
                    ]);
                }

                event(new NFeAutorizadaEvent($nfe));
                return $nfe->fresh();

            } else {
                DB::rollBack();
                $nfe->update([
                    'status' => 'rejeitada',
                    'informacoes_fisco' => "cStat: {$retorno['cStat']} — {$retorno['xMotivo']}",
                ]);
                throw new Exception("SEFAZ rejeitou a NF-e: [{$retorno['cStat']}] {$retorno['xMotivo']}");
            }

        } catch (Exception $e) {
            DB::rollBack();
            if ($nfe->status === NotaFiscal::STATUS_PROCESSANDO) {
                $nfe->update(['status' => 'rascunho']);
            }
            Log::error("NFeService::emitir — {$e->getMessage()}", ['nfe_id' => $nfe->id]);
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // CANCELAR
    // ──────────────────────────────────────────────────────────

    public function cancelar(NotaFiscal $nfe, string $justificativa): NotaFiscal
    {
        if ($nfe->status !== NotaFiscal::STATUS_AUTORIZADA) {
            throw new Exception('Apenas NF-e autorizadas podem ser canceladas.');
        }

        $horasDesdeEmissao = $nfe->data_autorizacao->diffInHours(now());
        if ($horasDesdeEmissao > 24) {
            throw new Exception('Prazo de cancelamento expirado (máximo 24 horas após autorização).');
        }

        $certificado = $this->obterCertificado($nfe->empresa);
        $retorno     = $this->sefaz->cancelarNFe(
            $nfe->chave_acesso,
            $nfe->protocolo,
            $justificativa,
            $certificado,
            $nfe->ambiente
        );

        if (in_array($retorno['cStat'], ['101', '135', '155'])) {
            $nfe->update([
                'status'              => NotaFiscal::STATUS_CANCELADA,
                'motivo_cancelamento' => $justificativa,
                'data_cancelamento'   => now(),
                'xml_cancelamento'    => $retorno['xml'] ?? null,
            ]);
            return $nfe->fresh();
        }

        throw new Exception("Cancelamento rejeitado pelo SEFAZ: [{$retorno['cStat']}] {$retorno['xMotivo']}");
    }

    // ──────────────────────────────────────────────────────────
    // CARTA DE CORREÇÃO
    // ──────────────────────────────────────────────────────────

    public function cartaCorrecao(NotaFiscal $nfe, string $descricao): array
    {
        if (! $nfe->pode_carta_correcao) {
            throw new Exception('Carta de Correção só pode ser emitida para NF-e autorizada.');
        }
        if (strlen($descricao) < 15) {
            throw new Exception('A descrição deve ter pelo menos 15 caracteres.');
        }

        $sequencia   = ($nfe->cartasCorrecao()->max('sequencia') ?? 0) + 1;
        $certificado = $this->obterCertificado($nfe->empresa);

        $retorno = $this->sefaz->cartaCorrecao(
            $nfe->chave_acesso,
            $descricao,
            $sequencia,
            $certificado,
            $nfe->ambiente
        );

        if ($retorno['cStat'] === '135') {
            $carta = $nfe->cartasCorrecao()->create([
                'sequencia'  => $sequencia,
                'descricao'  => $descricao,
                'protocolo'  => $retorno['nProt'],
            ]);
            return ['carta' => $carta, 'protocolo' => $retorno['nProt']];
        }

        throw new Exception("Carta de Correção rejeitada: [{$retorno['cStat']}] {$retorno['xMotivo']}");
    }

    // ──────────────────────────────────────────────────────────
    // CONSULTAR no SEFAZ
    // ──────────────────────────────────────────────────────────

    public function consultar(string $chave, Empresa $empresa): array
    {
        $certificado = $this->obterCertificado($empresa);
        return $this->sefaz->consultarNFe($chave, $certificado);
    }

    // ──────────────────────────────────────────────────────────
    // Métodos privados
    // ──────────────────────────────────────────────────────────

    private function inserirItens(NotaFiscal $nfe, Pedido $pedido): void
    {
        $simplesNacional = (int) $pedido->empresa->regime_tributario === 1;
        foreach ($pedido->itens as $i => $item) {
            $produto = $item->produto;
            $valorTotal = round((float) $item->total, 2);
            $icmsCst = $produto->icms_cst ?: ($simplesNacional ? '400' : '00');
            $icmsTributado = ! $simplesNacional && in_array($icmsCst, ['00', '000'], true);
            $icmsBase = $icmsTributado ? $valorTotal : 0;
            $icmsValor = $icmsTributado ? round($icmsBase * (float) $produto->icms_aliquota / 100, 2) : 0;
            $pisCst = $produto->pis_cst ?: ($simplesNacional ? '49' : '01');
            $cofinsCst = $produto->cofins_cst ?: ($simplesNacional ? '49' : '01');
            $pisBase = $pisCst === '01' ? $valorTotal : 0;
            $cofinsBase = $cofinsCst === '01' ? $valorTotal : 0;

            NotaFiscalItem::create([
                'nota_fiscal_id' => $nfe->id,
                'produto_id'     => $produto->id,
                'numero_item'    => $i + 1,
                'codigo'         => $produto->codigo ?? $produto->sku,
                'descricao'      => $item->descricao ?? $produto->nome,
                'ncm'            => $item->ncm ?? $produto->ncm,
                'cfop'           => $item->cfop ?? $produto->cfop ?? '5102',
                'unidade'        => $produto->unidade?->sigla ?? 'UN',
                'quantidade'     => $item->quantidade,
                'valor_unitario' => $item->preco_unitario,
                'valor_total'    => $valorTotal,
                'valor_desconto' => $item->desconto_valor,
                // Tributos do produto
                'icms_origem'    => $produto->origem ?? 0,
                'icms_cst'       => $icmsCst,
                'icms_base'      => $icmsBase,
                'icms_aliquota'  => $produto->icms_aliquota,
                'icms_valor'     => $icmsValor,
                'pis_cst'        => $pisCst,
                'pis_base'       => $pisBase,
                'pis_aliquota'   => $produto->pis_aliquota,
                'pis_valor'      => round($pisBase * (float) $produto->pis_aliquota / 100, 2),
                'cofins_cst'     => $cofinsCst,
                'cofins_base'    => $cofinsBase,
                'cofins_aliquota'=> $produto->cofins_aliquota,
                'cofins_valor'   => round($cofinsBase * (float) $produto->cofins_aliquota / 100, 2),
                'ipi_cst'        => $produto->ipi_cst,
                'ipi_aliquota'   => $produto->ipi_aliquota,
            ]);
        }
    }

    private function calcularTotais(NotaFiscal $nfe): void
    {
        $itens = $nfe->itens;

        $totais = [
            'valor_produtos'    => $itens->sum('valor_total'),
            'valor_desconto'    => $itens->sum('valor_desconto'),
            'base_calculo_icms' => $itens->sum('icms_base'),
            'valor_icms'        => $itens->sum('icms_valor'),
            'valor_ipi'         => $itens->sum('ipi_valor'),
            'base_pis'          => $itens->sum('pis_base'),
            'valor_pis'         => $itens->sum('pis_valor'),
            'base_cofins'       => $itens->sum('cofins_base'),
            'valor_cofins'      => $itens->sum('cofins_valor'),
        ];

        $totais['valor_total'] = $totais['valor_produtos']
            + ($nfe->valor_frete ?? 0)
            + ($nfe->outras_despesas ?? 0)
            + ($nfe->valor_seguro ?? 0)
            + $totais['valor_ipi']
            - $totais['valor_desconto'];

        $nfe->update($totais);
    }

    private function gerarXml(NotaFiscal $nfe): string
    {
        // Aqui entra a geração do XML NFe conforme manual SEFAZ
        // Na prática, usa biblioteca como sped-nfe ou nfephp
        // Este é o ponto de integração com a lib fiscal
        return app(XmlNFeGenerator::class)->gerar($nfe);
    }

    private function obterCertificado(Empresa $empresa): CertificadoDigital
    {
        $cert = CertificadoDigital::where('empresa_id', $empresa->id)
                    ->where('ativo', true)
                    ->where('validade', '>', now())
                    ->first();

        if (! $cert) {
            throw new Exception('Nenhum certificado digital válido encontrado para esta empresa.');
        }

        return $cert;
    }

    private function obterSerieBloqueada(Empresa $empresa, string $tipo): SerieFiscal
    {
        $serie = SerieFiscal::where('empresa_id', $empresa->id)
                    ->where('tipo', $tipo)
                    ->where('ativo', true)
                    ->lockForUpdate()
                    ->first();

        if (! $serie) {
            throw new Exception("Série fiscal não configurada para {$tipo}.");
        }

        return $serie;
    }

    private function montarXmlProcessado(string $xmlAssinado, array $retorno): string
    {
        $nfe = preg_replace('/^<\?xml[^>]+>\s*/', '', $xmlAssinado) ?: $xmlAssinado;
        $protocolo = htmlspecialchars((string) $retorno['nProt'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $chave = htmlspecialchars((string) $retorno['chNFe'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $motivo = htmlspecialchars((string) $retorno['xMotivo'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $recebimento = htmlspecialchars((string) ($retorno['dhRecbto'] ?? now()->toIso8601String()), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'.$nfe
            .'<protNFe versao="4.00"><infProt><tpAmb>'.$this->ambienteXml($retorno).'</tpAmb>'
            .'<verAplic>IntelbrasTech-ERP</verAplic><chNFe>'.$chave.'</chNFe><dhRecbto>'.$recebimento.'</dhRecbto>'
            .'<nProt>'.$protocolo.'</nProt><digVal></digVal><cStat>100</cStat><xMotivo>'.$motivo.'</xMotivo>'
            .'</infProt></protNFe></nfeProc>';
    }

    private function ambienteXml(array $retorno): int
    {
        return (int) ($retorno['tpAmb'] ?? config('services.sefaz.ambiente', 2));
    }

}
