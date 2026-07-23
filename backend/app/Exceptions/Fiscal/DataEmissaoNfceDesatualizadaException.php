<?php

namespace App\Exceptions\Fiscal;

use RuntimeException;

class DataEmissaoNfceDesatualizadaException extends RuntimeException
{
    public function __construct(
        public readonly string $dataVenda,
        public readonly string $dataEmissaoFiscal,
        public readonly string $dataAtual
    ) {
        parent::__construct(
            "A venda foi realizada em {$dataVenda} e a NFC-e está com emissão em {$dataEmissaoFiscal}. ".
            "Para transmitir em {$dataAtual}, confirme a atualização da data e hora fiscal."
        );
    }
}