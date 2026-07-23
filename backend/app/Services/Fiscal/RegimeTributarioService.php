<?php

namespace App\Services\Fiscal;

use App\Models\Empresa;
use DomainException;

class RegimeTributarioService
{
    public const APURACOES = [
        'mei',
        'simples_nacional',
        'simples_excesso',
        'lucro_presumido',
        'lucro_real',
        'arbitrado',
    ];

    public function crt(Empresa $empresa): int
    {
        return match ((string) $empresa->regime_apuracao) {
            'mei', 'simples_nacional' => 1,
            'simples_excesso' => 2,
            'lucro_presumido', 'lucro_real', 'arbitrado' => 3,
            default => $this->crtLegado($empresa),
        };
    }

    public function usaCsosn(Empresa $empresa): bool
    {
        return in_array($this->crt($empresa), [1, 2], true);
    }

    public function descricao(Empresa $empresa): string
    {
        return match ((string) $empresa->regime_apuracao) {
            'mei' => 'MEI — Microempreendedor Individual',
            'simples_nacional' => 'Simples Nacional',
            'simples_excesso' => 'Simples Nacional — excesso de sublimite',
            'lucro_presumido' => 'Regime Normal — Lucro Presumido',
            'lucro_real' => 'Regime Normal — Lucro Real',
            'arbitrado' => 'Regime Normal — Lucro Arbitrado',
            default => 'Regime fiscal ainda não confirmado',
        };
    }

    public function validarPerfil(Empresa $empresa): void
    {
        if (! $empresa->regime_fiscal_confirmado) {
            throw new DomainException(
                'Confirme o regime fiscal atual em Serviços > Empresa antes de emitir.'
            );
        }

        if (! in_array((string) $empresa->regime_apuracao, self::APURACOES, true)) {
            throw new DomainException(
                'Selecione a forma de tributação atual da empresa.'
            );
        }

        if ($this->usaCsosn($empresa)) {
            $this->normalizarCsosn($empresa->icms_csosn_padrao, false);
        } else {
            $this->normalizarCst($empresa->icms_cst_padrao, false);
        }

        $this->normalizarContribuicao($empresa->pis_cst_padrao, 'PIS');
        $this->normalizarContribuicao($empresa->cofins_cst_padrao, 'COFINS');
    }

    public function normalizarIcms(Empresa $empresa, ?string $codigo): string
    {
        $this->validarPerfil($empresa);

        if ($this->usaCsosn($empresa)) {
            $normalizado = $this->normalizarCsosn($codigo, true);

            if ($normalizado !== null) {
                return $normalizado;
            }

            return $this->normalizarCsosn($empresa->icms_csosn_padrao, false);
        }

        $normalizado = $this->normalizarCst($codigo, true);

        if ($normalizado !== null) {
            return $normalizado;
        }

        return $this->normalizarCst($empresa->icms_cst_padrao, false);
    }

    public function normalizarPis(Empresa $empresa, ?string $codigo): string
    {
        return $this->normalizarContribuicao(
            filled($codigo) ? $codigo : $empresa->pis_cst_padrao,
            'PIS'
        );
    }

    public function normalizarCofins(Empresa $empresa, ?string $codigo): string
    {
        return $this->normalizarContribuicao(
            filled($codigo) ? $codigo : $empresa->cofins_cst_padrao,
            'COFINS'
        );
    }

    private function crtLegado(Empresa $empresa): int
    {
        $regime = (int) $empresa->regime_tributario;

        if (! in_array($regime, [1, 2, 3], true)) {
            throw new DomainException(
                'Regime fiscal não configurado. Abra Serviços > Empresa e confirme a tributação atual.'
            );
        }

        return $regime;
    }

    private function normalizarCsosn(?string $codigo, bool $aceitarIncompativel): ?string
    {
        $numeros = preg_replace('/\D/', '', (string) $codigo);
        $normalizado = str_pad(ltrim($numeros, '0') ?: '', 3, '0', STR_PAD_LEFT);

        if (in_array($normalizado, ['101', '102', '103', '300', '400', '500', '900'], true)) {
            return $normalizado;
        }

        if ($aceitarIncompativel) {
            return null;
        }

        throw new DomainException(
            'Informe um CSOSN padrão válido: 101, 102, 103, 300, 400, 500 ou 900.'
        );
    }

    private function normalizarCst(?string $codigo, bool $aceitarIncompativel): ?string
    {
        $numeros = preg_replace('/\D/', '', (string) $codigo);
        $normalizado = str_pad(ltrim($numeros, '0') ?: '', 2, '0', STR_PAD_LEFT);

        if (in_array($normalizado, ['00', '10', '20', '30', '40', '41', '50', '51', '60', '70', '90'], true)) {
            return $normalizado;
        }

        if ($aceitarIncompativel) {
            return null;
        }

        throw new DomainException(
            'Informe um CST de ICMS padrão válido para o Regime Normal.'
        );
    }

    private function normalizarContribuicao(?string $codigo, string $tributo): string
    {
        $numeros = preg_replace('/\D/', '', (string) $codigo);
        $normalizado = str_pad(ltrim($numeros, '0') ?: '', 2, '0', STR_PAD_LEFT);

        $permitidos = [
            '01', '02', '03', '04', '05', '06', '07', '08', '09',
            '49', '50', '51', '52', '53', '54', '55', '56',
            '60', '61', '62', '63', '64', '65', '66', '67',
            '70', '71', '72', '73', '74', '75', '98', '99',
        ];

        if (! in_array($normalizado, $permitidos, true)) {
            throw new DomainException(
                "Informe um CST padrão válido para {$tributo}."
            );
        }

        return $normalizado;
    }
}
