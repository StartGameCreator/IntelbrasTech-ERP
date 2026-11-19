<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use DOMDocument;
use DOMXPath;
use Throwable;

class DanfeFiscalDataService
{
    public function extract(NotaFiscal $nota): array
    {
        $empty = [
            'ide' => [],
            'emitente' => [],
            'destinatario' => [],
            'totais' => [],
            'transportadora' => [],
            'veiculo' => [],
            'volumes' => [],
            'itens' => [],
            'pagamentos' => [],
            'inf_cpl' => '',
            'inf_ad_fisco' => '',
        ];

        $xml = trim((string) $nota->xml);
        if ($xml === '') {
            return $empty;
        }

        try {
            $previous = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                return $empty;
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $xp = new DOMXPath($dom);
            $value = static function (DOMXPath $xp, string $query, ?\DOMNode $context = null): string {
                $node = $xp->query($query, $context)->item(0);
                return $node ? trim((string) $node->nodeValue) : '';
            };
            $number = static function (string $value): float {
                return is_numeric($value) ? (float) $value : 0.0;
            };

            $result = $empty;

            $result['ide'] = [
                'natOp' => $value($xp, '//*[local-name()="ide"]/*[local-name()="natOp"]'),
                'mod' => $value($xp, '//*[local-name()="ide"]/*[local-name()="mod"]'),
                'serie' => $value($xp, '//*[local-name()="ide"]/*[local-name()="serie"]'),
                'nNF' => $value($xp, '//*[local-name()="ide"]/*[local-name()="nNF"]'),
                'dhEmi' => $value($xp, '//*[local-name()="ide"]/*[local-name()="dhEmi"]'),
                'dhSaiEnt' => $value($xp, '//*[local-name()="ide"]/*[local-name()="dhSaiEnt"]'),
                'tpNF' => $value($xp, '//*[local-name()="ide"]/*[local-name()="tpNF"]'),
            ];

            $result['emitente'] = [
                'xNome' => $value($xp, '//*[local-name()="emit"]/*[local-name()="xNome"]'),
                'xFant' => $value($xp, '//*[local-name()="emit"]/*[local-name()="xFant"]'),
                'CNPJ' => $value($xp, '//*[local-name()="emit"]/*[local-name()="CNPJ"]'),
                'IE' => $value($xp, '//*[local-name()="emit"]/*[local-name()="IE"]'),
                'IM' => $value($xp, '//*[local-name()="emit"]/*[local-name()="IM"]'),
            ];

            $result['destinatario'] = [
                'xNome' => $value($xp, '//*[local-name()="dest"]/*[local-name()="xNome"]'),
                'CNPJ' => $value($xp, '//*[local-name()="dest"]/*[local-name()="CNPJ"]'),
                'CPF' => $value($xp, '//*[local-name()="dest"]/*[local-name()="CPF"]'),
                'IE' => $value($xp, '//*[local-name()="dest"]/*[local-name()="IE"]'),
                'xLgr' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="xLgr"]'),
                'nro' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="nro"]'),
                'xCpl' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="xCpl"]'),
                'xBairro' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="xBairro"]'),
                'CEP' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="CEP"]'),
                'xMun' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="xMun"]'),
                'UF' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="UF"]'),
                'fone' => $value($xp, '//*[local-name()="dest"]//*[local-name()="enderDest"]/*[local-name()="fone"]'),
            ];

            $result['totais'] = [
                'vBC' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vBC"]')),
                'vICMS' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vICMS"]')),
                'vBCST' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vBCST"]')),
                'vST' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vST"]')),
                'vFCPST' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vFCPST"]')),
                'vProd' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vProd"]')),
                'vFrete' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vFrete"]')),
                'vSeg' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vSeg"]')),
                'vDesc' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vDesc"]')),
                'vOutro' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vOutro"]')),
                'vIPI' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vIPI"]')),
                'vNF' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vNF"]')),
                'vTotTrib' => $number($value($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vTotTrib"]')),
                'vServ' => $number($value($xp, '//*[local-name()="ISSQNtot"]/*[local-name()="vServ"]')),
                'vBCISS' => $number($value($xp, '//*[local-name()="ISSQNtot"]/*[local-name()="vBC"]')),
                'vISS' => $number($value($xp, '//*[local-name()="ISSQNtot"]/*[local-name()="vISS"]')),
            ];

            $result['transportadora'] = [
                'modFrete' => $value($xp, '//*[local-name()="transp"]/*[local-name()="modFrete"]'),
                'xNome' => $value($xp, '//*[local-name()="transporta"]/*[local-name()="xNome"]'),
                'CNPJ' => $value($xp, '//*[local-name()="transporta"]/*[local-name()="CNPJ"]'),
                'CPF' => $value($xp, '//*[local-name()="transporta"]/*[local-name()="CPF"]'),
                'IE' => $value($xp, '//*[local-name()="transporta"]/*[local-name()="IE"]'),
                'xEnder' => $value($xp, '//*[local-name()="transporta"]/*[local-name()="xEnder"]'),
                'xMun' => $value($xp, '//*[local-name()="transporta"]/*[local-name()="xMun"]'),
                'UF' => $value($xp, '//*[local-name()="transporta"]/*[local-name()="UF"]'),
            ];

            $result['veiculo'] = [
                'placa' => $value($xp, '//*[local-name()="veicTransp"]/*[local-name()="placa"]'),
                'UF' => $value($xp, '//*[local-name()="veicTransp"]/*[local-name()="UF"]'),
                'RNTC' => $value($xp, '//*[local-name()="veicTransp"]/*[local-name()="RNTC"]'),
            ];

            $vol = $xp->query('//*[local-name()="vol"]')->item(0);
            if ($vol) {
                $result['volumes'] = [
                    'qVol' => $value($xp, './*[local-name()="qVol"]', $vol),
                    'esp' => $value($xp, './*[local-name()="esp"]', $vol),
                    'marca' => $value($xp, './*[local-name()="marca"]', $vol),
                    'nVol' => $value($xp, './*[local-name()="nVol"]', $vol),
                    'pesoB' => $value($xp, './*[local-name()="pesoB"]', $vol),
                    'pesoL' => $value($xp, './*[local-name()="pesoL"]', $vol),
                ];
            }

            foreach ($xp->query('//*[local-name()="det"]') as $det) {
                $result['itens'][] = [
                    'codigo' => $value($xp, './/*[local-name()="prod"]/*[local-name()="cProd"]', $det),
                    'descricao' => $value($xp, './/*[local-name()="prod"]/*[local-name()="xProd"]', $det),
                    'ncm' => $value($xp, './/*[local-name()="prod"]/*[local-name()="NCM"]', $det),
                    'cst' => $value($xp, './/*[local-name()="ICMS"]//*[local-name()="CSOSN" or local-name()="CST"]', $det),
                    'cfop' => $value($xp, './/*[local-name()="prod"]/*[local-name()="CFOP"]', $det),
                    'unidade' => $value($xp, './/*[local-name()="prod"]/*[local-name()="uCom"]', $det),
                    'quantidade' => $number($value($xp, './/*[local-name()="prod"]/*[local-name()="qCom"]', $det)),
                    'unitario' => $number($value($xp, './/*[local-name()="prod"]/*[local-name()="vUnCom"]', $det)),
                    'total' => $number($value($xp, './/*[local-name()="prod"]/*[local-name()="vProd"]', $det)),
                    'bc_icms' => $number($value($xp, './/*[local-name()="ICMS"]//*[local-name()="vBC"]', $det)),
                    'v_icms' => $number($value($xp, './/*[local-name()="ICMS"]//*[local-name()="vICMS"]', $det)),
                    'v_ipi' => $number($value($xp, './/*[local-name()="IPI"]//*[local-name()="vIPI"]', $det)),
                    'p_icms' => $number($value($xp, './/*[local-name()="ICMS"]//*[local-name()="pICMS"]', $det)),
                    'p_ipi' => $number($value($xp, './/*[local-name()="IPI"]//*[local-name()="pIPI"]', $det)),
                    'origem' => $value($xp, './/*[local-name()="ICMS"]//*[local-name()="orig"]', $det),
                    'v_tot_trib' => $number($value($xp, './/*[local-name()="imposto"]/*[local-name()="vTotTrib"]', $det)),
                    'v_ii' => $number($value($xp, './/*[local-name()="II"]/*[local-name()="vII"]', $det)),
                ];
            }

            foreach ($xp->query('//*[local-name()="detPag"]') as $pag) {
                $result['pagamentos'][] = [
                    'tipo' => $value($xp, './*[local-name()="tPag"]', $pag),
                    'valor' => $number($value($xp, './*[local-name()="vPag"]', $pag)),
                ];
            }

            $result['inf_cpl'] = $value($xp, '//*[local-name()="infAdic"]/*[local-name()="infCpl"]');
            $result['inf_ad_fisco'] = $value($xp, '//*[local-name()="infAdic"]/*[local-name()="infAdFisco"]');

            return $result;
        } catch (Throwable) {
            return $empty;
        }
    }
}
