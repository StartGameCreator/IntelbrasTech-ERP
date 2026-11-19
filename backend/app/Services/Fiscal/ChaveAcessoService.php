<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use InvalidArgumentException;

class ChaveAcessoService
{
    public function gerar(NotaFiscal $nfe, ?string $codigoNumerico = null): string
    {
        $empresa = $nfe->empresa;
        $cnpj = $this->numeros($nfe->emitente_cnpj ?: $empresa?->cnpj);
        if (strlen($cnpj) !== 14) {
            throw new InvalidArgumentException('CNPJ do emitente inválido para gerar a chave de acesso.');
        }

        $base = $this->codigoUf($empresa?->estado)
            . $nfe->data_emissao->format('ym')
            . $cnpj
            . str_pad((string) ($nfe->modelo ?: '55'), 2, '0', STR_PAD_LEFT)
            . str_pad((string) $nfe->serie, 3, '0', STR_PAD_LEFT)
            . str_pad((string) $nfe->numero, 9, '0', STR_PAD_LEFT)
            . '1'
            . str_pad($codigoNumerico ?: (string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        return $base . $this->digitoVerificador($base);
    }

    public function digitoVerificador(string $base): int
    {
        if (! preg_match('/^\d{43}$/', $base)) {
            throw new InvalidArgumentException('A base da chave deve possuir 43 dígitos.');
        }

        $peso = 2;
        $soma = 0;
        for ($i = 42; $i >= 0; $i--) {
            $soma += ((int) $base[$i]) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;
        return ($resto === 0 || $resto === 1) ? 0 : 11 - $resto;
    }

    private function numeros(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?: '';
    }

    private function codigoUf(?string $uf): string
    {
        return [
            'AC'=>'12','AL'=>'27','AP'=>'16','AM'=>'13','BA'=>'29','CE'=>'23','DF'=>'53','ES'=>'32',
            'GO'=>'52','MA'=>'21','MT'=>'51','MS'=>'50','MG'=>'31','PA'=>'15','PB'=>'25','PR'=>'41',
            'PE'=>'26','PI'=>'22','RJ'=>'33','RN'=>'24','RS'=>'43','RO'=>'11','RR'=>'14','SC'=>'42',
            'SP'=>'35','SE'=>'28','TO'=>'17',
        ][strtoupper((string) $uf)] ?? throw new InvalidArgumentException('UF do emitente inválida.');
    }
}
