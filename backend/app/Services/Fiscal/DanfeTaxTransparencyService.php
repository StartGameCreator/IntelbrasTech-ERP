<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use App\Models\Produto;
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

        // 1. Valores oficiais já gravados pelo IBPT nas informações adicionais.
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
        $valorNota = max((float) ($nota->valor_total ?: 0), $xml['vNF']);

        // 2. Se o XML possuir vTotTrib, usa o total fiscal autorizado.
        if ($total <= 0 && $xml['vTotTrib'] > 0) {
            $total = $xml['vTotTrib'];
        }

        $fonte = stripos($texto, 'IBPT') !== false ? 'IBPT' : 'XML fiscal';

        // 3. Usa percentual_tributos dos produtos da nota, quando cadastrado.
        if ($total <= 0) {
            $produtoCalc = $this->calculateFromProducts($nota);
            if ($produtoCalc['total'] > 0) {
                $total = $produtoCalc['total'];
                $percentualTotal = $produtoCalc['percentual_total'];
                $fonte = 'Cadastro fiscal dos produtos';
            }
        }

        $produtoImportado = count(array_intersect([1, 2, 3, 6, 7, 8], $xml['origens'])) > 0
            || $xml['vII'] > 0
            || $this->hasImportedProduct($nota);

        // 4. Fallback configurável. Evita DANFE com carga tributária falsamente zerada
        // quando o XML não recebeu vTotTrib e o produto ainda não possui taxa IBPT.
        if ($total <= 0 && $valorNota > 0) {
            $federalRate = (float) config(
                $produtoImportado
                    ? 'danfe_tributos.federal_importado'
                    : 'danfe_tributos.federal_nacional',
                16.14
            );
            $estadualRate = (float) config('danfe_tributos.estadual', 19.00);
            $municipalRate = (float) config('danfe_tributos.municipal', 0.00);

            $federais = round($valorNota * $federalRate / 100, 2);
            $estaduais = round($valorNota * $estadualRate / 100, 2);
            $municipais = round($valorNota * $municipalRate / 100, 2);
            $total = round($federais + $estaduais + $municipais, 2);

            $percentualFederal = $federalRate;
            $percentualEstadual = $estadualRate;
            $percentualMunicipal = $municipalRate;
            $percentualTotal = $federalRate + $estadualRate + $municipalRate;
            $fonte = 'Referência tributária configurada no ERP';
        }

        // Quando existe apenas o total, reparte pelas proporções configuradas,
        // sem usar ICMS/IPI destacados da operação como carga tributária.
        if ($total > 0 && $federais <= 0 && $estaduais <= 0 && $municipais <= 0) {
            $federalRate = (float) config(
                $produtoImportado
                    ? 'danfe_tributos.federal_importado'
                    : 'danfe_tributos.federal_nacional',
                16.14
            );
            $estadualRate = (float) config('danfe_tributos.estadual', 19.00);
            $municipalRate = (float) config('danfe_tributos.municipal', 0.00);
            $sumRate = $federalRate + $estadualRate + $municipalRate;

            if ($sumRate > 0) {
                $federais = round($total * $federalRate / $sumRate, 2);
                $estaduais = round($total * $estadualRate / $sumRate, 2);
                $municipais = round(max(0, $total - $federais - $estaduais), 2);
            }
        }

        return [
            'federais' => $federais,
            'estaduais' => $estaduais,
            'municipais' => $municipais,
            'total' => $total,
            'percentual_federal' => $percentualFederal ?: $this->percent($federais, $valorNota),
            'percentual_estadual' => $percentualEstadual ?: $this->percent($estaduais, $valorNota),
            'percentual_municipal' => $percentualMunicipal ?: $this->percent($municipais, $valorNota),
            'percentual_total' => $percentualTotal ?: $this->percent($total, $valorNota),
            'composicao_informada' => true,
            'produto_importado' => $produtoImportado,
            'ii_destacado_xml' => $xml['vII'],
            'tributos_importacao_inclusos_total' => $produtoImportado && $total > 0,
            'fonte' => $fonte,
        ];
    }

    private function calculateFromProducts(NotaFiscal $nota): array
    {
        $totalTributos = 0.0;
        $baseTotal = 0.0;

        $nota->loadMissing('itens');

        foreach ($nota->itens as $item) {
            $base = (float) $item->valor_total;
            if ($base <= 0) {
                continue;
            }

            $produto = $item->produto_id ? Produto::query()->find($item->produto_id) : null;
            $percentual = (float) ($produto?->percentual_tributos ?? 0);

            if ($percentual > 0) {
                $totalTributos += $base * $percentual / 100;
                $baseTotal += $base;
            }
        }

        return [
            'total' => round($totalTributos, 2),
            'percentual_total' => $baseTotal > 0 ? ($totalTributos / $baseTotal) * 100 : 0.0,
        ];
    }

    private function hasImportedProduct(NotaFiscal $nota): bool
    {
        $nota->loadMissing('itens');

        foreach ($nota->itens as $item) {
            $origem = (int) ($item->icms_origem ?? 0);

            if (in_array($origem, [1, 2, 3, 6, 7, 8], true)) {
                return true;
            }

            if ($item->produto_id) {
                $produtoOrigem = (int) Produto::query()
                    ->whereKey($item->produto_id)
                    ->value('origem');

                if (in_array($produtoOrigem, [1, 2, 3, 6, 7, 8], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function xmlTotals(string $xml): array
    {
        $result = ['vTotTrib' => 0.0, 'vNF' => 0.0, 'vII' => 0.0, 'origens' => []];

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
            $xp = new DOMXPath($dom);

            $sum = static function (DOMXPath $xp, string $query): float {
                $total = 0.0;
                foreach ($xp->query($query) as $node) {
                    $value = trim($node->nodeValue);
                    if (is_numeric($value)) {
                        $total += (float) $value;
                    }
                }
                return $total;
            };

            $first = static function (DOMXPath $xp, string $query): float {
                $node = $xp->query($query)->item(0);
                $value = $node ? trim($node->nodeValue) : '';
                return is_numeric($value) ? (float) $value : 0.0;
            };

            $result['vTotTrib'] = $first($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vTotTrib"]');

            if ($result['vTotTrib'] <= 0) {
                $result['vTotTrib'] = $sum(
                    $xp,
                    '//*[local-name()="det"]/*[local-name()="imposto"]/*[local-name()="vTotTrib"]'
                );
            }

            $result['vNF'] = $first($xp, '//*[local-name()="ICMSTot"]/*[local-name()="vNF"]');
            $result['vII'] = $sum($xp, '//*[local-name()="II"]/*[local-name()="vII"]');

            foreach ($xp->query('//*[local-name()="ICMS"]//*[local-name()="orig"]') as $orig) {
                $result['origens'][] = (int) trim($orig->nodeValue);
            }
        } catch (Throwable) {
            // Mantém fallback para cadastro/configuração.
        }

        return $result;
    }

    private function xmlAdditionalInfo(string $xml): string
    {
        if (trim($xml) === '') {
            return '';
        }

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

            foreach ($xp->query(
                '//*[local-name()="infAdic"]/*[local-name()="infCpl" or local-name()="infAdFisco"]'
            ) as $node) {
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
            if (preg_match($pattern, $text, $match)) {
                return $this->number($match[1]);
            }
        }

        return 0.0;
    }

    private function percentFromPattern(string $text, string $pattern): float
    {
        return preg_match($pattern, $text, $match)
            ? $this->number($match[1])
            : 0.0;
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
