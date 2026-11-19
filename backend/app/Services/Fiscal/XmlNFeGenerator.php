<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use App\Models\NotaFiscal;
use DomainException;

class XmlNFeGenerator
{
    public function __construct(
        private readonly RegimeTributarioService $regime
    ) {}

    public function gerar(NotaFiscal $nfe): string
    {
        $nfe->loadMissing(['empresa', 'itens']);

        if (! preg_match('/^\d{44}$/', (string) $nfe->chave_acesso)) {
            throw new DomainException('A chave de acesso deve ser gerada antes do XML.');
        }

        $empresa = $nfe->empresa;

        if (! $empresa) {
            throw new DomainException('A nota fiscal não possui empresa vinculada.');
        }

        $modelo = (string) ($nfe->modelo ?: ($nfe->tipo === 'NFCe' ? '65' : '55'));
        $crt = $this->regime->crt($empresa);
        $cNF = substr((string) $nfe->chave_acesso, 35, 8);
        $cDV = substr((string) $nfe->chave_acesso, -1);
        $nfceAnonima = $modelo === '65' && blank($nfe->destinatario_cpf_cnpj);
        $idDest = $nfceAnonima || strtoupper((string) $empresa->estado) === strtoupper((string) $nfe->destinatario_uf)
            ? 1
            : 2;
        $interestadual = $idDest === 2;

        $itens = '';

        foreach ($nfe->itens as $item) {
            $itens .= $this->item($item, $empresa, $interestadual);
        }

        $destinatario = $nfceAnonima ? '' : $this->destinatario($nfe);
        $infAdic = $nfe->informacoes_adicionais
            ? '<infAdic><infCpl>'.$this->e($nfe->informacoes_adicionais).'</infCpl></infAdic>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
            .'<infNFe Id="NFe'.$nfe->chave_acesso.'" versao="4.00">'
            .'<ide>'
            .'<cUF>'.$this->codigoUf($empresa->estado).'</cUF>'
            .'<cNF>'.$cNF.'</cNF>'
            .'<natOp>'.$this->e($nfe->natureza_operacao).'</natOp>'
            .'<mod>'.$modelo.'</mod>'
            .'<serie>'.(int) $nfe->serie.'</serie>'
            .'<nNF>'.(int) $nfe->numero.'</nNF>'
            .'<dhEmi>'.$nfe->data_emissao->format('Y-m-d\TH:i:sP').'</dhEmi>'
            .($nfe->chave_referenciada ? '<NFref><refNFe>'.$this->n($nfe->chave_referenciada).'</refNFe></NFref>' : '')
            .'<tpNF>'.((int) $nfe->finalidade === 4 ? '0' : '1').'</tpNF>'
            .'<idDest>'.$idDest.'</idDest>'
            .'<cMunFG>'.$this->n($empresa->ibge).'</cMunFG>'
            .'<tpImp>'.($modelo === '65' ? '4' : '1').'</tpImp>'
            .'<tpEmis>1</tpEmis>'
            .'<cDV>'.$cDV.'</cDV>'
            .'<tpAmb>'.(int) $nfe->ambiente.'</tpAmb>'
            .'<finNFe>'.(int) $nfe->finalidade.'</finNFe>'
            .'<indFinal>1</indFinal>'
            .'<indPres>1</indPres>'
            .'<procEmi>0</procEmi>'
            .'<verProc>IntelbrasTechERP1.0</verProc>'
            .'</ide>'
            .$this->emitente($nfe, $empresa, $crt)
            .$destinatario
            .$itens
            .$this->total($nfe)
            .'<transp><modFrete>'.($nfe->tipo_frete ?: '9').'</modFrete></transp>'
            .$this->pagamento($nfe)
            .$infAdic
            .'</infNFe>'
            .'</NFe>';
    }

    private function emitente(NotaFiscal $nfe, Empresa $empresa, int $crt): string
    {
        return '<emit>'
            .'<CNPJ>'.$this->n($nfe->emitente_cnpj).'</CNPJ>'
            .'<xNome>'.$this->e($nfe->emitente_razao).'</xNome>'
            .'<enderEmit>'
            .'<xLgr>'.$this->e($empresa->logradouro).'</xLgr>'
            .'<nro>'.$this->e($empresa->numero ?: 'S/N').'</nro>'
            .($empresa->complemento ? '<xCpl>'.$this->e($empresa->complemento).'</xCpl>' : '')
            .'<xBairro>'.$this->e($empresa->bairro).'</xBairro>'
            .'<cMun>'.$this->n($empresa->ibge).'</cMun>'
            .'<xMun>'.$this->e($empresa->cidade).'</xMun>'
            .'<UF>'.$this->e($empresa->estado).'</UF>'
            .'<CEP>'.$this->n($empresa->cep).'</CEP>'
            .'<cPais>1058</cPais><xPais>BRASIL</xPais>'
            .($empresa->telefone ? '<fone>'.$this->n($empresa->telefone).'</fone>' : '')
            .'</enderEmit>'
            .'<IE>'.$this->n($nfe->emitente_ie).'</IE>'
            .'<CRT>'.$crt.'</CRT>'
            .'</emit>';
    }

    private function destinatario(NotaFiscal $nfe): string
    {
        $doc = $this->documentoXml($nfe->destinatario_cpf_cnpj);

        return '<dest>'
            .$doc
            .'<xNome>'.$this->e($nfe->destinatario_nome).'</xNome>'
            .'<enderDest>'
            .'<xLgr>'.$this->e($nfe->destinatario_logradouro).'</xLgr>'
            .'<nro>'.$this->e($nfe->destinatario_numero ?: 'S/N').'</nro>'
            .($nfe->destinatario_complemento ? '<xCpl>'.$this->e($nfe->destinatario_complemento).'</xCpl>' : '')
            .'<xBairro>'.$this->e($nfe->destinatario_bairro).'</xBairro>'
            .'<cMun>'.$this->n($nfe->destinatario_ibge).'</cMun>'
            .'<xMun>'.$this->e($nfe->destinatario_cidade).'</xMun>'
            .'<UF>'.$this->e($nfe->destinatario_uf).'</UF>'
            .'<CEP>'.$this->n($nfe->destinatario_cep).'</CEP>'
            .'<cPais>1058</cPais><xPais>BRASIL</xPais>'
            .'</enderDest>'
            .'<indIEDest>'.($nfe->destinatario_ie ? '1' : '9').'</indIEDest>'
            .($nfe->destinatario_ie ? '<IE>'.$this->n($nfe->destinatario_ie).'</IE>' : '')
            .($nfe->destinatario_email ? '<email>'.$this->e($nfe->destinatario_email).'</email>' : '')
            .'</dest>';
    }

    private function item($item, Empresa $empresa, bool $interestadual): string
    {
        $codigoIcms = $this->regime->normalizarIcms($empresa, $item->icms_cst);
        $ean = 'SEM GTIN';
        $vProd = $this->f($item->valor_total);

        $icms = $this->regime->usaCsosn($empresa)
            ? $this->icmsSimples($item, $codigoIcms)
            : $this->icmsNormal($item, $codigoIcms);

        $pis = $this->pisCofins('PIS', $item->pis_cst, $item->pis_base, $item->pis_aliquota, $item->pis_valor);
        $cofins = $this->pisCofins('COFINS', $item->cofins_cst, $item->cofins_base, $item->cofins_aliquota, $item->cofins_valor);

        return '<det nItem="'.(int) $item->numero_item.'">'
            .'<prod>'
            .'<cProd>'.$this->e($item->codigo).'</cProd>'
            .'<cEAN>'.$ean.'</cEAN>'
            .'<xProd>'.$this->e($item->descricao).'</xProd>'
            .'<NCM>'.$this->n($item->ncm).'</NCM>'
            .'<CFOP>'.$this->cfopPorDestino($item->cfop, $interestadual).'</CFOP>'
            .'<uCom>'.$this->e(strtoupper((string) $item->unidade)).'</uCom>'
            .'<qCom>'.$this->f($item->quantidade, 4).'</qCom>'
            .'<vUnCom>'.$this->f($item->valor_unitario, 10).'</vUnCom>'
            .'<vProd>'.$vProd.'</vProd>'
            .'<cEANTrib>'.$ean.'</cEANTrib>'
            .'<uTrib>'.$this->e(strtoupper((string) $item->unidade)).'</uTrib>'
            .'<qTrib>'.$this->f($item->quantidade, 4).'</qTrib>'
            .'<vUnTrib>'.$this->f($item->valor_unitario, 10).'</vUnTrib>'
            .($item->valor_desconto > 0 ? '<vDesc>'.$this->f($item->valor_desconto).'</vDesc>' : '')
            .'<indTot>1</indTot>'
            .'</prod>'
            .'<imposto><ICMS>'.$icms.'</ICMS>'.$pis.$cofins.'</imposto>'
            .'</det>';
    }

    private function icmsSimples($item, string $csosn): string
    {
        $origem = (int) $item->icms_origem;

        return match ($csosn) {
            '101' => '<ICMSSN101><orig>'.$origem.'</orig><CSOSN>101</CSOSN>'
                .'<pCredSN>'.$this->f($item->icms_aliquota, 4).'</pCredSN>'
                .'<vCredICMSSN>'.$this->f($item->icms_valor).'</vCredICMSSN>'
                .'</ICMSSN101>',

            // O leiaute 4.00 agrupa os CSOSN 102, 103, 300 e 400 na
            // tag ICMSSN102; não existe elemento XML ICMSSN400 no XSD.
            '102', '103', '300', '400' => '<ICMSSN102><orig>'.$origem.'</orig><CSOSN>'.$csosn.'</CSOSN></ICMSSN102>',

            '500' => '<ICMSSN500><orig>'.$origem.'</orig><CSOSN>500</CSOSN>'
                .'<vBCSTRet>0.00</vBCSTRet>'
                .'<pST>0.0000</pST>'
                .'<vICMSSubstituto>0.00</vICMSSubstituto>'
                .'<vICMSSTRet>0.00</vICMSSTRet>'
                .'</ICMSSN500>',

            '900' => '<ICMSSN900><orig>'.$origem.'</orig><CSOSN>900</CSOSN>'
                .'<modBC>3</modBC>'
                .'<vBC>'.$this->f($item->icms_base).'</vBC>'
                .'<pRedBC>0.0000</pRedBC>'
                .'<pICMS>'.$this->f($item->icms_aliquota, 4).'</pICMS>'
                .'<vICMS>'.$this->f($item->icms_valor).'</vICMS>'
                .'</ICMSSN900>',

            default => throw new DomainException("CSOSN {$csosn} ainda não suportado pelo gerador."),
        };
    }

    private function icmsNormal($item, string $cst): string
    {
        $origem = (int) $item->icms_origem;

        return match ($cst) {
            '00' => '<ICMS00><orig>'.$origem.'</orig><CST>00</CST>'
                .'<modBC>3</modBC>'
                .'<vBC>'.$this->f($item->icms_base).'</vBC>'
                .'<pICMS>'.$this->f($item->icms_aliquota, 4).'</pICMS>'
                .'<vICMS>'.$this->f($item->icms_valor).'</vICMS>'
                .'</ICMS00>',

            '40', '41', '50' => '<ICMS40><orig>'.$origem.'</orig><CST>'.$cst.'</CST></ICMS40>',

            '60' => '<ICMS60><orig>'.$origem.'</orig><CST>60</CST>'
                .'<vBCSTRet>0.00</vBCSTRet>'
                .'<pST>0.0000</pST>'
                .'<vICMSSubstituto>0.00</vICMSSubstituto>'
                .'<vICMSSTRet>0.00</vICMSSTRet>'
                .'</ICMS60>',

            default => throw new DomainException("CST {$cst} ainda não suportado pelo gerador."),
        };
    }

    private function pisCofins(string $nome, ?string $cst, $base, $aliquota, $valor): string
    {
        $cst = str_pad((string) ($cst ?: '49'), 2, '0', STR_PAD_LEFT);

        if ($cst === '01') {
            return "<{$nome}><{$nome}Aliq><CST>01</CST>"
                ."<vBC>{$this->f($base)}</vBC>"
                ."<p{$nome}>{$this->f($aliquota, 4)}</p{$nome}>"
                ."<v{$nome}>{$this->f($valor)}</v{$nome}>"
                ."</{$nome}Aliq></{$nome}>";
        }

        return "<{$nome}><{$nome}Outr><CST>{$cst}</CST>"
            ."<vBC>0.00</vBC><p{$nome}>0.0000</p{$nome}><v{$nome}>0.00</v{$nome}>"
            ."</{$nome}Outr></{$nome}>";
    }

    private function total(NotaFiscal $nfe): string
    {
        return '<total><ICMSTot>'
            .'<vBC>'.$this->f($nfe->base_calculo_icms).'</vBC>'
            .'<vICMS>'.$this->f($nfe->valor_icms).'</vICMS>'
            .'<vICMSDeson>0.00</vICMSDeson>'
            .'<vFCP>0.00</vFCP>'
            .'<vBCST>0.00</vBCST>'
            .'<vST>0.00</vST>'
            .'<vFCPST>0.00</vFCPST>'
            .'<vFCPSTRet>0.00</vFCPSTRet>'
            .'<vProd>'.$this->f($nfe->valor_produtos).'</vProd>'
            .'<vFrete>'.$this->f($nfe->valor_frete).'</vFrete>'
            .'<vSeg>'.$this->f($nfe->valor_seguro).'</vSeg>'
            .'<vDesc>'.$this->f($nfe->valor_desconto).'</vDesc>'
            .'<vII>0.00</vII>'
            .'<vIPI>'.$this->f($nfe->valor_ipi).'</vIPI>'
            .'<vIPIDevol>0.00</vIPIDevol>'
            .'<vPIS>'.$this->f($nfe->valor_pis).'</vPIS>'
            .'<vCOFINS>'.$this->f($nfe->valor_cofins).'</vCOFINS>'
            .'<vOutro>'.$this->f($nfe->outras_despesas).'</vOutro>'
            .'<vNF>'.$this->f($nfe->valor_total).'</vNF>'
            .'<vTotTrib>0.00</vTotTrib>'
            .'</ICMSTot></total>';
    }

    private function pagamento(NotaFiscal $nfe): string
    {
        return '<pag><detPag>'
            .'<tPag>99</tPag>'
            .'<xPag>Outros</xPag>'
            .'<vPag>'.$this->f($nfe->valor_total).'</vPag>'
            .'</detPag></pag>';
    }

    private function documentoXml(?string $documento): string
    {
        $doc = $this->n($documento);

        return strlen($doc) === 11
            ? '<CPF>'.$doc.'</CPF>'
            : '<CNPJ>'.$doc.'</CNPJ>';
    }

    private function e($value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function n($value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?: '';
    }

    private function f($value, int $casas = 2): string
    {
        return number_format((float) $value, $casas, '.', '');
    }

    private function codigoUf(?string $uf): string
    {
        return [
            'AC'=>'12','AL'=>'27','AP'=>'16','AM'=>'13','BA'=>'29','CE'=>'23',
            'DF'=>'53','ES'=>'32','GO'=>'52','MA'=>'21','MT'=>'51','MS'=>'50',
            'MG'=>'31','PA'=>'15','PB'=>'25','PR'=>'41','PE'=>'26','PI'=>'22',
            'RJ'=>'33','RN'=>'24','RS'=>'43','RO'=>'11','RR'=>'14','SC'=>'42',
            'SP'=>'35','SE'=>'28','TO'=>'17',
        ][strtoupper((string) $uf)] ?? '';
    }

    private function cfopPorDestino(?string $cfop, bool $interestadual): string
    {
        $codigo = $this->n($cfop);

        if ($interestadual && preg_match('/^5\d{3}$/', $codigo)) {
            return '6'.substr($codigo, 1);
        }

        if (! $interestadual && preg_match('/^6\d{3}$/', $codigo)) {
            return '5'.substr($codigo, 1);
        }

        return $codigo;
    }
}
