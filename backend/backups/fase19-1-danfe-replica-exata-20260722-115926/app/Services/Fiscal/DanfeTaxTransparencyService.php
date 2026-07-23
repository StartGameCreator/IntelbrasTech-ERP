<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use DOMDocument;
use DOMXPath;
use Throwable;

class DanfeTaxTransparencyService
{
    public function calculate(NotaFiscal $nota): array
    {
        $xml = $this->xmlTotals((string) $nota->xml);
        $text = $this->textTotals((string) $nota->informacoes_adicionais);
        $totalNota = (float) ($nota->valor_total ?: 0);

        $pis = $this->firstPositive((float) ($nota->valor_pis ?: 0), $xml['pis']);
        $cofins = $this->firstPositive((float) ($nota->valor_cofins ?: 0), $xml['cofins']);
        $ipi = $this->firstPositive((float) ($nota->valor_ipi ?: 0), $xml['ipi']);
        $ii = $xml['ii'];
        $iof = $xml['iof'];
        $afrmm = $xml['afrmm'];
        $despesasAduaneiras = $xml['despesas_aduaneiras'];

        $icms = $this->firstPositive((float) ($nota->valor_icms ?: 0), $xml['icms']);
        $icmsSt = $xml['icms_st'];
        $fcp = $xml['fcp'];
        $fcpSt = $xml['fcp_st'];
        $iss = $xml['iss'];

        $federaisCalculados = $pis + $cofins + $ipi + $ii + $iof + $afrmm + $despesasAduaneiras;
        $estaduaisCalculados = $icms + $icmsSt + $fcp + $fcpSt;
        $municipaisCalculados = $iss;

        $federais = $this->firstPositive($text['federais'], $federaisCalculados);
        $estaduais = $this->firstPositive($text['estaduais'], $estaduaisCalculados);
        $municipais = $this->firstPositive($text['municipais'], $municipaisCalculados);
        $total = $this->firstPositive($text['total'], $xml['total_tributos'], $federais + $estaduais + $municipais);

        $origensImportadas = [1, 2, 3, 6, 7, 8];
        $produtoImportado = count(array_intersect($origensImportadas, $xml['origens'])) > 0
            || $ii > 0
            || $afrmm > 0
            || $despesasAduaneiras > 0
            || $xml['tem_declaracao_importacao'];

        return [
            'federais' => $federais,
            'estaduais' => $estaduais,
            'municipais' => $municipais,
            'total' => $total,
            'percentual_federal' => $this->percent($text['percentual_federal'], $federais, $totalNota),
            'percentual_estadual' => $this->percent($text['percentual_estadual'], $estaduais, $totalNota),
            'percentual_municipal' => $this->percent($text['percentual_municipal'], $municipais, $totalNota),
            'percentual_total' => $this->percent($text['percentual_total'], $total, $totalNota),
            'icms' => $icms,
            'icms_st' => $icmsSt,
            'fcp' => $fcp,
            'fcp_st' => $fcpSt,
            'iss' => $iss,
            'ii' => $ii,
            'ipi' => $ipi,
            'pis' => $pis,
            'cofins' => $cofins,
            'iof' => $iof,
            'afrmm' => $afrmm,
            'despesas_aduaneiras' => $despesasAduaneiras,
            'produto_importado' => $produtoImportado,
            'fonte' => $text['fonte'] ?: 'IBPT / XML fiscal',
        ];
    }

    private function xmlTotals(string $xml): array
    {
        $result = [
            'icms' => 0.0, 'icms_st' => 0.0, 'fcp' => 0.0, 'fcp_st' => 0.0,
            'ipi' => 0.0, 'ii' => 0.0, 'pis' => 0.0, 'cofins' => 0.0,
            'iof' => 0.0, 'iss' => 0.0, 'afrmm' => 0.0,
            'despesas_aduaneiras' => 0.0, 'total_tributos' => 0.0,
            'origens' => [], 'tem_declaracao_importacao' => false,
        ];

        if (trim($xml) === '') {
            return $result;
        }

        try {
            $previous = libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                return $result;
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $xpath = new DOMXPath($dom);
            $result['icms'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vICMS"]');
            $result['icms_st'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vST"]');
            $result['fcp'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vFCP"]');
            $result['fcp_st'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vFCPST"]');
            $result['ipi'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vIPI"]');
            $result['ii'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vII"]');
            $result['pis'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vPIS"]');
            $result['cofins'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vCOFINS"]');
            $result['iof'] = $this->xpathSum($xpath, '//*[local-name()="II"]/*[local-name()="vIOF"]');
            $result['iss'] = $this->xpathSum($xpath, '//*[local-name()="ISSQNtot"]/*[local-name()="vISS"]');
            $result['afrmm'] = $this->xpathSum($xpath, '//*[local-name()="DI"]/*[local-name()="vAFRMM"]');
            $result['despesas_aduaneiras'] = $this->xpathSum($xpath, '//*[local-name()="DI"]//*[local-name()="vDespAdu"]');
            $result['total_tributos'] = $this->xpathSum($xpath, '//*[local-name()="ICMSTot"]/*[local-name()="vTotTrib"]');
            $result['tem_declaracao_importacao'] = $xpath->query('//*[local-name()="DI"]')->length > 0;

            foreach ($xpath->query('//*[local-name()="prod"]/*[local-name()="orig"]') as $node) {
                $result['origens'][] = (int) trim($node->textContent);
            }
        } catch (Throwable) {
            return $result;
        }

        return $result;
    }

    private function textTotals(string $text): array
    {
        $result = [
            'federais' => 0.0, 'estaduais' => 0.0, 'municipais' => 0.0, 'total' => 0.0,
            'percentual_federal' => 0.0, 'percentual_estadual' => 0.0,
            'percentual_municipal' => 0.0, 'percentual_total' => 0.0,
            'fonte' => null,
        ];

        $patterns = [
            'total' => '/Total aproximado de tributos:\s*R\$\s*([0-9.]+,[0-9]{2})(?:\s*\(([0-9.,]+)%\))?/iu',
            'federais' => '/Federais\s*R\$\s*([0-9.]+,[0-9]{2})(?:\s*\(([0-9.,]+)%\))?/iu',
            'estaduais' => '/Estaduais\s*R\$\s*([0-9.]+,[0-9]{2})(?:\s*\(([0-9.,]+)%\))?/iu',
            'municipais' => '/Municipais\s*R\$\s*([0-9.]+,[0-9]{2})(?:\s*\(([0-9.,]+)%\))?/iu',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $result[$key] = $this->brNumber($matches[1] ?? null);
                $percentKey = $key === 'total' ? 'percentual_total' : 'percentual_'.rtrim($key, 's');
                if ($key === 'federais') $percentKey = 'percentual_federal';
                if ($key === 'estaduais') $percentKey = 'percentual_estadual';
                if ($key === 'municipais') $percentKey = 'percentual_municipal';
                $result[$percentKey] = $this->brNumber($matches[2] ?? null);
            }
        }

        if (preg_match('/Fonte\s*:?\s*([^.;\r\n]+)/iu', $text, $matches)) {
            $result['fonte'] = trim($matches[1]);
        }

        return $result;
    }

    private function xpathSum(DOMXPath $xpath, string $expression): float
    {
        $sum = 0.0;
        foreach ($xpath->query($expression) as $node) {
            $sum += (float) str_replace(',', '.', trim($node->textContent));
        }
        return round($sum, 2);
    }

    private function firstPositive(float ...$values): float
    {
        foreach ($values as $value) {
            if ($value > 0) return round($value, 2);
        }
        return 0.0;
    }

    private function percent(float $informed, float $value, float $total): float
    {
        if ($informed > 0) return round($informed, 2);
        return $total > 0 ? round(($value / $total) * 100, 2) : 0.0;
    }

    private function brNumber(?string $value): float
    {
        if (! $value) return 0.0;
        return (float) str_replace(',', '.', str_replace('.', '', $value));
    }
}
