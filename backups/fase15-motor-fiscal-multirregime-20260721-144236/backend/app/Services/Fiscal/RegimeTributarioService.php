<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use DomainException;

class RegimeTributarioService
{
    public function crt(Empresa $empresa): int
    {
        $porte = mb_strtoupper(trim((string) $empresa->porte_empresa));
        $regime = (int) $empresa->regime_tributario;

        if (
            str_contains($porte, 'MEI') ||
            str_contains($porte, 'MICROEMPREENDEDOR INDIVIDUAL')
        ) {
            return 1;
        }

        if (in_array($regime, [1, 2, 3], true)) {
            return $regime;
        }

        throw new DomainException(
            'Regime tributário inválido. Informe Simples Nacional, Simples com excesso de sublimite ou Regime Normal.'
        );
    }

    public function usaCsosn(Empresa $empresa): bool
    {
        return in_array($this->crt($empresa), [1, 2], true);
    }

    public function normalizarIcms(Empresa $empresa, ?string $codigo): string
    {
        $numeros = preg_replace('/\D/', '', (string) $codigo);

        if ($this->usaCsosn($empresa)) {
            $codigo = str_pad(ltrim($numeros, '0') ?: '400', 3, '0', STR_PAD_LEFT);

            if (! in_array($codigo, ['101', '102', '103', '300', '400', '500', '900'], true)) {
                throw new DomainException("CSOSN {$codigo} inválido para empresa do Simples Nacional.");
            }

            return $codigo;
        }

        $codigo = str_pad(ltrim($numeros, '0') ?: '0', 2, '0', STR_PAD_LEFT);

        if (! in_array($codigo, ['00', '10', '20', '30', '40', '41', '50', '51', '60', '70', '90'], true)) {
            throw new DomainException("CST {$codigo} inválido para empresa do Regime Normal.");
        }

        return $codigo;
    }
}
