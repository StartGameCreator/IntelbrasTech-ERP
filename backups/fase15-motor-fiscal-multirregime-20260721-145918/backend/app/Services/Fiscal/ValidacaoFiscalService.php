<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use DomainException;

class ValidacaoFiscalService
{
    public function __construct(
        private readonly RegimeTributarioService $regime
    ) {}

    public function validarParaEmissao(NotaFiscal $nfe): void
    {
        $nfe->loadMissing(['empresa', 'itens']);
        $erros = [];
        $empresa = $nfe->empresa;

        try {
            $this->regime->validarPerfil($empresa);
        } catch (DomainException $e) {
            $erros[] = $e->getMessage();
        }

        $this->obrigatorio($erros, 'CNPJ do emitente', $this->documento($nfe->emitente_cnpj) === 14);
        $this->obrigatorio($erros, 'Inscrição Estadual do emitente', filled($nfe->emitente_ie));
        $this->obrigatorio($erros, 'razão social do emitente', filled($nfe->emitente_razao));
        $this->obrigatorio($erros, 'UF do emitente', strlen((string) $empresa?->estado) === 2);
        $this->obrigatorio($erros, 'código IBGE do emitente', strlen($this->numeros($empresa?->ibge)) === 7);
        $this->obrigatorio($erros, 'CEP do emitente', strlen($this->numeros($empresa?->cep)) === 8);

        $nfceAnonima = $nfe->modelo === '65' && blank($nfe->destinatario_cpf_cnpj);

        if (! $nfceAnonima) {
            $docDest = $this->documento($nfe->destinatario_cpf_cnpj);
            $this->obrigatorio($erros, 'CPF/CNPJ do destinatário', in_array($docDest, [11, 14], true));

            foreach ([
                'nome do destinatário' => $nfe->destinatario_nome,
                'logradouro do destinatário' => $nfe->destinatario_logradouro,
                'número do destinatário' => $nfe->destinatario_numero,
                'bairro do destinatário' => $nfe->destinatario_bairro,
                'cidade do destinatário' => $nfe->destinatario_cidade,
            ] as $nome => $valor) {
                $this->obrigatorio($erros, $nome, filled($valor));
            }

            $this->obrigatorio($erros, 'UF do destinatário', strlen((string) $nfe->destinatario_uf) === 2);
            $this->obrigatorio($erros, 'código IBGE do destinatário', strlen($this->numeros($nfe->destinatario_ibge)) === 7);
            $this->obrigatorio($erros, 'CEP do destinatário', strlen($this->numeros($nfe->destinatario_cep)) === 8);
        }

        $this->obrigatorio($erros, 'ao menos um item', $nfe->itens->isNotEmpty());

        foreach ($nfe->itens as $item) {
            $prefixo = "Item {$item->numero_item}";

            $this->obrigatorio($erros, "$prefixo: NCM com 8 dígitos", strlen($this->numeros($item->ncm)) === 8);
            $this->obrigatorio($erros, "$prefixo: CFOP com 4 dígitos", strlen($this->numeros($item->cfop)) === 4);
            $this->obrigatorio($erros, "$prefixo: unidade comercial", filled($item->unidade));
            $this->obrigatorio($erros, "$prefixo: quantidade positiva", (float) $item->quantidade > 0);
            $this->obrigatorio($erros, "$prefixo: valor total positivo", (float) $item->valor_total > 0);

            try {
                $this->regime->normalizarIcms($empresa, $item->icms_cst);
                $this->regime->normalizarPis($empresa, $item->pis_cst);
                $this->regime->normalizarCofins($empresa, $item->cofins_cst);
            } catch (DomainException $e) {
                $erros[] = "{$prefixo}: {$e->getMessage()}";
            }
        }

        if ($erros) {
            throw new DomainException("NF-e com pendências fiscais:\n- " . implode("\n- ", array_unique($erros)));
        }
    }

    private function obrigatorio(array &$erros, string $campo, bool $valido): void
    {
        if (! $valido) {
            $erros[] = "Informe/corrija {$campo}.";
        }
    }

    private function documento(?string $valor): int
    {
        return strlen($this->numeros($valor));
    }

    private function numeros(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?: '';
    }
}
