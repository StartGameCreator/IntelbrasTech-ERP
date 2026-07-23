<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Credenciais de marketplace (Mercado Livre, Shopee) ficam centralizadas
    // em config/integracoes.php, consumido diretamente pelos Services de integração.

    'sefaz' => [
        'ambiente' => env('SEFAZ_AMBIENTE', 2), // 1 = Produção, 2 = Homologação
    ],

];
