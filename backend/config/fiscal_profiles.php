<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Perfil fiscal MEI
    |--------------------------------------------------------------------------
    |
    | O padrão inicial é CSOSN 102. A empresa pode alterar para 400 na tela
    | fiscal sem editar código. O cadastro específico do item/produto tem
    | prioridade sobre este padrão.
    |
    */
    'mei' => [
        'crt' => 1,
        'csosn_padrao' => '102',
        'csosn_permitidos' => ['102', '400'],
        'pis_cst_padrao' => '49',
        'cofins_cst_padrao' => '49',
        'cfop_padrao' => '5102',
        'natureza_operacao' => 'PADRAO MEI',
        'icms_aliquota' => 0,
        'ipi_aliquota' => 0,
    ],
];
