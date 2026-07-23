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
        $texto = trim(implode(' ', array_filter([
            (string) $nota->informacoes_adicionais,
            (string) $nota->informacoes_fisco,
            $this->xmlAdditionalInfo((string) $nota->xml),
        ])));

        $total = $this->moneyFromPatterns($texto, [
            '/Total\s+aproximado\s+de\s+tributos\s*:\s*R\$\s*([\d\.,]+)/iu',
            '/Valor\s+aproximado\s+dos\s+tributos\s*:\s*R\$\s*([\d\.,]+)/iu',
        ]);

        $federais = $this->moneyFromPatterns($texto, [
            '/Federais?\s+R\$\s*([\d\.,]+)/iu',
            '/Tributos?\s+Federais?\s*:\s*R\$\s*([\d\.,]+)/iu',
        ]);

        $estaduais = $this->moneyFromPatterns($texto, [
            '/Estaduais?\s+R\$\s*([\d\.,]+)/iu',
            '/Tributos?\s+Estaduais?\s*:\s*R\$\s*([\d\.,]+)/iu',
        ]);

        $municipais = $this->moneyFromPatterns($texto, [
            '/Municipais?\s+R\$\s*([\d\.,]+)/iu',
            '/Tributos?\s+Municipais?\s*:\s*R\$\s*([\d\.,]+)/iu',
        ]);

        $percentualTotal = $this->percentFromPattern($texto, '/Total\s+aproximado\s+de\s+tributos.*?\(([\d\.,]+)%\)/iu');
        $percentualFederal = $this->percentFromPattern($texto, '/Federais?\s+R\$.*?\(([\d\.,]+)%\)/iu');
        $percentualEstadual = $this->percentFromPattern($texto, '/Estaduais?\s+R\$.*?\(([\d\.,]+)%\)/iu');
        $percentualMunicipal = $this->percentFromPattern($texto, '/Municipais?\s+R\$.*?\(([\d\.,]+)%\)/iu');

        $xml = $this->xmlTotals((string) $nota->xml);
        if ($total <= 0) {
            $total = $xml['vTotTrib'];
        }

        $valorNota = max((float) ($nota->valor_total ?: 0), $xml['vNF']);

        if ($total > 0 && $federais <= 0 && $estaduais <= 0 && $municipais <= 0) {
            // Não inventa a divisão. Exibe o total oficial do XML e deixa a composição como não informada.
            $composicaoInformada = false;
        } else {
            $composicaoInformada = true;
        }

        $origensImportadas = [1, 2, 3, 6, 7, 8];
        $produtoImportado = count(array_intersect($origensImportadas, $xml['origens'])) > 0 || $xml['vII'] > 0;

        return [
            'federais' => $federais,
            'estaduais' => $estaduais,
            'municipais' => $municipais,
            'total' => $total,
            'percentual_federal' => $percentualFederal ?: $this->percent($federais, $valorNota),
            'percentual_estadual' => $percentualEstadual ?: $this->percent($estaduais, $valorNota),
            'percentual_municipal' => $percentualMunicipal ?: $this->percent($municipais, $valorNota),
            'percentual_total' => $percentualTotal ?: $this->percent($total, $valorNota),
            'composicao_informada' => $composicaoInformada,
            'produto_importado' => $produtoImportado,
            'ii_destacado_xml' => $xml['vII'],
            'tributos_importacao_inclusos_total' => $produtoImportado && $total > 0,
            'fonte' => stripos($texto, 'IBPT') !== false ? 'IBPT' : 'XML fiscal / dados adicionais',
        ];
    }

    private function xmlTotals(string $xml): array
    {
        $result = ['vTotTrib' => 0.0, 'vNF' => 0.0, 'vII' => 0.0, 'origens' => []];
        if (trim($xml) === '') return $result;

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
            $xp = new DOMXPath($dom);

            $sum = static function (DOMXPath $xp, string $query): float {
                $total = 0.0;
                foreach ($xp->query($query) as $node) {
                    if (is_numeric(trim($node->nodeValue))) $total += (float) trim($node->nodeValue);
                }
                return $total;
            };

            $first = static function (DOMXPath $xp, string $query): float {
                $node = $xp->query($query)->item(0);
                return $node && is_numeric(trim($node->nodeValue)) ? (float) trim($node->nodeValue) : 0.0;
            };

            $result['vTotTrib'] = $first($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vTotTrib"]');
            if ($result['vTotTrib'] <= 0) {
                $result['vTotTrib'] = $sum($xp, '//*[local-name()="det"]/*[local-name()="imposto"]/*[local-name()="vTotTrib"]');
            }
            $result['vNF'] = $first($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vNF"]');
            $result['vII'] = $sum($xp, '//*[local-name()="II"]/*[local-name()="vII"]');
            foreach ($xp->query('//*[local-name()="ICMS"]//*[local-name()="orig"]') as $orig) {
                $result['origens'][] = (int) trim($orig->nodeValue);
            }
        } catch (Throwable) {}

        return $result;
    }

    private function xmlAdditionalInfo(string $xml): string
    {
        if (trim($xml) === '') return '';
        try {
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            if (! $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                return '';
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xp = new DOMXPath($dom);
            $parts = [];
            foreach ($xp->query('//*[local-name()="infAdic"]/*[local-name()="infCpl" or local-name()="infAdFisco"]') as $node) {
                $parts[] = trim($node->nodeValue);
            }
            return implode(' ', $parts);
        } catch (Throwable) {
            return '';
        }
    }

    private function moneyFromPatterns(string $text, array $patterns): float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) return $this->number($m[1]);
        }
        return 0.0;
    }

    private function percentFromPattern(string $text, string $pattern): float
    {
        return preg_match($pattern, $text, $m) ? $this->number($m[1]) : 0.0;
    }

    private function number(string $value): float
    {
        $value = preg_replace('/[^\d,.\-]/', '', $value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }
        return (float) str_replace(',', '.', $value);
    }

    private function percent(float $value, float $base): float
    {
        return $base > 0 ? ($value / $base) * 100 : 0.0;
    }
}
