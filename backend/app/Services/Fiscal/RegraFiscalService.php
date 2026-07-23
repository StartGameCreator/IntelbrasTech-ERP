<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use Illuminate\Database\Eloquent\Model;

final class RegraFiscalService
{
    public const CSOSN_MEI_PADRAO = '102';
    public const CSOSN_MEI_ALTERNATIVO = '400';

    public function perfilEmpresa(?Model $empresa): array
    {
        $regime = (string) ($empresa?->regime_apuracao ?? '');
        $perfil = (string) ($empresa?->perfil_fiscal ?? '');

        $isMei = in_array($regime, ['mei', 'simei'], true)
            || in_array($perfil, ['mei', 'mei_regular'], true);

        if (!$isMei) {
            return [
                'ativo' => false,
                'regime' => $regime,
                'crt' => (int) ($empresa?->regime_tributario ?? 0),
            ];
        }

        $csosn = str_pad(
            preg_replace('/\D+/', '', (string) ($empresa?->mei_csosn_padrao ?? self::CSOSN_MEI_PADRAO)),
            3,
            '0',
            STR_PAD_LEFT
        );

        if (!in_array($csosn, [self::CSOSN_MEI_PADRAO, self::CSOSN_MEI_ALTERNATIVO], true)) {
            $csosn = self::CSOSN_MEI_PADRAO;
        }

        return [
            'ativo' => true,
            'regime' => 'mei',
            'crt' => 1,
            'csosn' => $csosn,
            'pis_cst' => '49',
            'cofins_cst' => '49',
            'cfop' => '5102',
            'natureza_operacao' => 'PADRAO MEI',
            'icms_aliquota' => 0.0,
            'ipi_aliquota' => 0.0,
        ];
    }

    public function valorItemOuPadrao(mixed $valorItem, mixed $valorProduto, mixed $padrao): mixed
    {
        if ($valorItem !== null && $valorItem !== '') {
            return $valorItem;
        }

        if ($valorProduto !== null && $valorProduto !== '') {
            return $valorProduto;
        }

        return $padrao;
    }
}
