<?php

namespace App\Services\Fiscal;

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
        $crt = $this->regime->crt($empresa);
        $cNF = substr($nfe->chave_acesso, 35, 8);
        $cDV = substr($nfe->chave_acesso, -1);
        $modelo = (string) ($nfe->modelo ?: '55');
        $nfceAnonima = $modelo === '65' && blank($nfe->destinatario_cpf_cnpj);
        $idDest = $nfceAnonima || strtoupper((string) $empresa->estado) === strtoupper((string) $nfe->destinatario_uf) ? 1 : 2;
        $docDest = $nfceAnonima ? '' : $this->documentoXml($nfe->destinatario_cpf_cnpj);

        $itens = '';
        foreach ($nfe->itens as $item) {
            $itens .= $this->item($item, $crt, $empresa);
        }

        $infAdic = $nfe->informacoes_adicionais
            ? '<infAdic><infCpl>'.$this->e($nfe->informacoes_adicionais).'</infCpl></infAdic>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<NFe xmlns="http://www.portalfiscal.inf.br/nfe">'
            .'<infNFe Id="NFe'.$nfe->chave_acesso.'" versao="4.00">'
            .'<ide>'
            .'<cUF>'.$this->codigoUf($empresa->estado).'</cUF><cNF>'.$cNF.'</cNF>'
            .'<natOp>'.$this->e($nfe->natureza_operacao).'</natOp><mod>'.$modelo.'</mod>'
            .'<serie>'.(int) $nfe->serie.'</serie><nNF>'.(int) $nfe->numero.'</nNF>'
            .'<dhEmi>'.$nfe->data_emissao->format('Y-m-d\TH:i:sP').'</dhEmi>'
            .'<tpNF>1</tpNF><idDest>'.$idDest.'</idDest><cMunFG>'.$this->n($empresa->ibge).'</cMunFG>'
            .'<tpImp>'.($modelo === '65' ? '4' : '1').'</tpImp><tpEmis>1</tpEmis><cDV>'.$cDV.'</cDV><tpAmb>'.(int) $nfe->ambiente.'</tpAmb>'
            .'<finNFe>'.(int) $nfe->finalidade.'</finNFe><indFinal>1</indFinal><indPres>1</indPres><procEmi>0</procEmi><verProc>IntelbrasTechERP1.0</verProc>'
            .'</ide>'
            .'<emit><CNPJ>'.$this->n($nfe->emitente_cnpj).'</CNPJ><xNome>'.$this->e($nfe->emitente_razao).'</xNome>'
            .'<enderEmit><xLgr>'.$this->e($empresa->logradouro).'</xLgr><nro>'.$this->e($empresa->numero ?: 'S/N').'</nro>'
            .($empresa->complemento ? '<xCpl>'.$this->e($empresa->complemento).'</xCpl>' : '')
            .'<xBairro>'.$this->e($empresa->bairro).'</xBairro><cMun>'.$this->n($empresa->ibge).'</cMun><xMun>'.$this->e($empresa->cidade).'</xMun>'
            .'<UF>'.$this->e($empresa->estado).'</UF><CEP>'.$this->n($empresa->cep).'</CEP><cPais>1058</cPais><xPais>BRASIL</xPais>'
            .($empresa->telefone ? '<fone>'.$this->n($empresa->telefone).'</fone>' : '').'</enderEmit>'
            .'<IE>'.$this->n($nfe->emitente_ie).'</IE><CRT>'.$crt.'</CRT></emit>'
            .($nfceAnonima ? '' : '<dest>'.$docDest.'<xNome>'.$this->e($nfe->destinatario_nome).'</xNome><enderDest>'
            .'<xLgr>'.$this->e($nfe->destinatario_logradouro).'</xLgr><nro>'.$this->e($nfe->destinatario_numero ?: 'S/N').'</nro>'
            .($nfe->destinatario_complemento ? '<xCpl>'.$this->e($nfe->destinatario_complemento).'</xCpl>' : '')
            .'<xBairro>'.$this->e($nfe->destinatario_bairro).'</xBairro><cMun>'.$this->n($nfe->destinatario_ibge).'</cMun>'
            .'<xMun>'.$this->e($nfe->destinatario_cidade).'</xMun><UF>'.$this->e($nfe->destinatario_uf).'</UF>'
            .'<CEP>'.$this->n($nfe->destinatario_cep).'</CEP><cPais>1058</cPais><xPais>BRASIL</xPais></enderDest>'
            .'<indIEDest>'.($nfe->destinatario_ie ? '1' : '9').'</indIEDest>'
            .($nfe->destinatario_ie ? '<IE>'.$this->n($nfe->destinatario_ie).'</IE>' : '')
            .($nfe->destinatario_email ? '<email>'.$this->e($nfe->destinatario_email).'</email>' : '').'</dest>')
            .$itens.$this->total($nfe).'<transp><modFrete>'.($nfe->tipo_frete ?: '9').'</modFrete></transp>'
            .'<pag><detPag><tPag>99</tPag><xPag>Outros</xPag><vPag>'.$this->f($nfe->valor_total).'</vPag></detPag></pag>'
            .$infAdic.'</infNFe></NFe>';
    }

    private function item($item, int $crt, $empresa): string
    {
        $item->icms_cst = $this->regime->normalizarIcms($empresa, $item->icms_cst);
        $vProd = $this->f($item->valor_total);
        $ean = 'SEM GTIN';
        $icms = $crt === 1
            ? $this->icmsSimplesNacional($item)
            : $this->icmsRegimeNormal($item);
        $pis = $this->pisCofins('PIS', $item->pis_cst, $item->pis_base, $item->pis_aliquota, $item->pis_valor);
        $cofins = $this->pisCofins('COFINS', $item->cofins_cst, $item->cofins_base, $item->cofins_aliquota, $item->cofins_valor);

        return '<det nItem="'.(int) $item->numero_item.'"><prod><cProd>'.$this->e($item->codigo).'</cProd>'
            .'<cEAN>'.$ean.'</cEAN><xProd>'.$this->e($item->descricao).'</xProd><NCM>'.$this->n($item->ncm).'</NCM>'
            .'<CFOP>'.$this->n($item->cfop).'</CFOP><uCom>'.$this->e(strtoupper($item->unidade)).'</uCom>'
            .'<qCom>'.$this->f($item->quantidade, 4).'</qCom><vUnCom>'.$this->f($item->valor_unitario, 10).'</vUnCom><vProd>'.$vProd.'</vProd>'
            .'<cEANTrib>'.$ean.'</cEANTrib><uTrib>'.$this->e(strtoupper($item->unidade)).'</uTrib><qTrib>'.$this->f($item->quantidade, 4).'</qTrib>'
            .'<vUnTrib>'.$this->f($item->valor_unitario, 10).'</vUnTrib><indTot>1</indTot></prod>'
            .'<imposto><ICMS>'.$icms.'</ICMS>'.$pis.$cofins.'</imposto></det>';
    }

    private function icmsSimplesNacional($item): string
    {
        $origem = (int) $item->icms_origem;
        $csosn = ltrim(preg_replace('/\D/', '', (string) $item->icms_cst), '0');
        $csosn = str_pad($csosn ?: '400', 3, '0', STR_PAD_LEFT);

        return match ($csosn) {
            '101' => '<ICMSSN101><orig>'.$origem.'</orig><CSOSN>101</CSOSN>'
                .'<pCredSN>'.$this->f($item->icms_aliquota, 4).'</pCredSN>'
                .'<vCredICMSSN>'.$this->f($item->icms_valor).'</vCredICMSSN></ICMSSN101>',

            '102', '103', '300' => '<ICMSSN102><orig>'.$origem.'</orig><CSOSN>'.$csosn.'</CSOSN></ICMSSN102>',

            '400' => '<ICMSSN400><orig>'.$origem.'</orig><CSOSN>400</CSOSN></ICMSSN400>',

            '500' => '<ICMSSN500><orig>'.$origem.'</orig><CSOSN>500</CSOSN>'
                .'<vBCSTRet>0.00</vBCSTRet><pST>0.0000</pST><vICMSSubstituto>0.00</vICMSSubstituto>'
                .'<vICMSSTRet>0.00</vICMSSTRet></ICMSSN500>',

            '900' => '<ICMSSN900><orig>'.$origem.'</orig><CSOSN>900</CSOSN>'
                .'<modBC>3</modBC><vBC>'.$this->f($item->icms_base).'</vBC>'
                .'<pRedBC>0.0000</pRedBC><pICMS>'.$this->f($item->icms_aliquota, 4).'</pICMS>'
                .'<vICMS>'.$this->f($item->icms_valor).'</vICMS></ICMSSN900>',

            default => throw new DomainException(
                "CSOSN {$csosn} não suportado pelo gerador fiscal. Revise o cadastro tributário do produto."
            ),
        };
    }

    private function icmsRegimeNormal($item): string
    {
        $origem = (int) $item->icms_origem;
        $cst = str_pad(
            ltrim(preg_replace('/\D/', '', (string) $item->icms_cst), '0') ?: '0',
            2,
            '0',
            STR_PAD_LEFT
        );

        return match ($cst) {
            '00' => '<ICMS00><orig>'.$origem.'</orig><CST>00</CST><modBC>3</modBC>'
                .'<vBC>'.$this->f($item->icms_base).'</vBC>'
                .'<pICMS>'.$this->f($item->icms_aliquota, 4).'</pICMS>'
                .'<vICMS>'.$this->f($item->icms_valor).'</vICMS></ICMS00>',

            '40', '41', '50' => '<ICMS40><orig>'.$origem.'</orig><CST>'.$cst.'</CST></ICMS40>',

            default => throw new DomainException(
                "CST {$cst} não suportado pelo gerador fiscal. Revise o cadastro tributário do produto."
            ),
        };
    }
    private function pisCofins(string $nome, ?string $cst, $base, $aliquota, $valor): string
    {
        $cst = str_pad((string) ($cst ?: '49'), 2, '0', STR_PAD_LEFT);
        if ($cst === '01') {
            return "<{$nome}><{$nome}Aliq><CST>01</CST><vBC>{$this->f($base)}</vBC><p{$nome}>{$this->f($aliquota, 4)}</p{$nome}><v{$nome}>{$this->f($valor)}</v{$nome}></{$nome}Aliq></{$nome}>";
        }
        return "<{$nome}><{$nome}Outr><CST>{$cst}</CST><vBC>0.00</vBC><p{$nome}>0.0000</p{$nome}><v{$nome}>0.00</v{$nome}></{$nome}Outr></{$nome}>";
    }

    private function total(NotaFiscal $nfe): string
    {
        return '<total><ICMSTot><vBC>'.$this->f($nfe->base_calculo_icms).'</vBC><vICMS>'.$this->f($nfe->valor_icms).'</vICMS>'
            .'<vICMSDeson>0.00</vICMSDeson><vFCP>0.00</vFCP><vBCST>0.00</vBCST><vST>0.00</vST><vFCPST>0.00</vFCPST><vFCPSTRet>0.00</vFCPSTRet>'
            .'<vProd>'.$this->f($nfe->valor_produtos).'</vProd><vFrete>'.$this->f($nfe->valor_frete).'</vFrete><vSeg>'.$this->f($nfe->valor_seguro).'</vSeg>'
            .'<vDesc>'.$this->f($nfe->valor_desconto).'</vDesc><vII>0.00</vII><vIPI>'.$this->f($nfe->valor_ipi).'</vIPI><vIPIDevol>0.00</vIPIDevol>'
            .'<vPIS>'.$this->f($nfe->valor_pis).'</vPIS><vCOFINS>'.$this->f($nfe->valor_cofins).'</vCOFINS><vOutro>'.$this->f($nfe->outras_despesas).'</vOutro>'
            .'<vNF>'.$this->f($nfe->valor_total).'</vNF><vTotTrib>0.00</vTotTrib></ICMSTot></total>';
    }

    private function documentoXml(?string $documento): string
    {
        $doc = $this->n($documento);
        return strlen($doc) === 11 ? '<CPF>'.$doc.'</CPF>' : '<CNPJ>'.$doc.'</CNPJ>';
    }
    private function e($v): string { return htmlspecialchars(trim((string) $v), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function n($v): string { return preg_replace('/\D/', '', (string) $v) ?: ''; }
    private function f($v, int $casas = 2): string { return number_format((float) $v, $casas, '.', ''); }
    private function codigoUf(?string $uf): string
    {
        return ['AC'=>'12','AL'=>'27','AP'=>'16','AM'=>'13','BA'=>'29','CE'=>'23','DF'=>'53','ES'=>'32','GO'=>'52','MA'=>'21','MT'=>'51','MS'=>'50','MG'=>'31','PA'=>'15','PB'=>'25','PR'=>'41','PE'=>'26','PI'=>'22','RJ'=>'33','RN'=>'24','RS'=>'43','RO'=>'11','RR'=>'14','SC'=>'42','SP'=>'35','SE'=>'28','TO'=>'17'][strtoupper((string) $uf)] ?? '';
    }
}
