<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transparência Tributária — fallback
    |--------------------------------------------------------------------------
    |
    | Estes percentuais são usados somente quando:
    | 1. a nota não possui texto IBPT;
    | 2. o XML não possui vTotTrib;
    | 3. o produto não possui percentual_tributos cadastrado.
    |
    | Ajuste no .env quando possuir a tabela IBPT específica da empresa/UF.
    */
    'federal_nacional' => (float) env('DANFE_TRIB_FEDERAL_NACIONAL', 16.14),
    'federal_importado' => (float) env('DANFE_TRIB_FEDERAL_IMPORTADO', 16.14),
    'estadual' => (float) env('DANFE_TRIB_ESTADUAL', 19.00),
    'municipal' => (float) env('DANFE_TRIB_MUNICIPAL', 0.00),
];
