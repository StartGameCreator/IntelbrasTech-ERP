<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use DomainException;

class RegimeTributarioService
{
    /**
     * Resolve o CRT fiscal sem usar valor padrão silencioso.
     *
     * 1 = Simples Nacional
     * 2 = Simples Nacional - excesso de sublimite
     * 3 = Regime Normal
     */
    public function crt(Empresa $empresa): int
    {
        $regime = (int) $empresa->regime_tributario;

        if (in_array($regime, [1, 2, 3], true)) {
            return $regime;
        }

        $porte = mb_strtoupper(trim((string) $empresa->porte_empresa));
        $atividade = mb_strtoupper(trim((string) $empresa->atividade_principal));

        if (
            str_contains($porte, 'MEI') ||
            str_contains($porte, 'MICROEMPREENDEDOR INDIVIDUAL') ||
            str_contains($atividade, 'MEI')
        ) {
            return 1;
        }

        throw new DomainException(
            'Regime tributário da empresa inválido. Defina Simples Nacional, Simples com excesso ou Regime Normal.'
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
            $codigo = ltrim($numeros, '0') ?: '400';
            $codigo = str_pad($codigo, 3, '0', STR_PAD_LEFT);

            if (! in_array($codigo, ['101', '102', '103', '300', '400', '500', '900'], true)) {
                throw new DomainException(
                    "CSOSN {$codigo} incompatível com o Simples Nacional."
                );
            }

            return $codigo;
        }

        $codigo = ltrim($numeros, '0') ?: '0';
        $codigo = str_pad($codigo, 2, '0', STR_PAD_LEFT);

        if (! in_array($codigo, ['00', '10', '20', '30', '40', '41', '50', '51', '60', '70', '90'], true)) {
            throw new DomainException(
                "CST {$codigo} incompatível com o Regime Normal."
            );
        }

        return $codigo;
    }
}
