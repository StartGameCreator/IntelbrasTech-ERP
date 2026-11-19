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
        'driver' => env('FISCAL_DRIVER', 'fake'),
        'allow_fake_in_production' => env('FISCAL_ALLOW_FAKE_IN_PRODUCTION', false),
        'storage_disk' => env('FISCAL_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'schemas' => env('FISCAL_SCHEMAS', 'PL_010_V1.30'),
        'nfce_qrcode_url' => env('SEFAZ_NFCE_QRCODE_URL', ''),
        'nfce_consulta_url' => env('SEFAZ_NFCE_CONSULTA_URL', ''),
        'nfe_consulta_url' => env(
            'SEFAZ_NFE_CONSULTA_URL',
            'https://nfeweb.sefaz.go.gov.br/nfeweb/sites/nfe/consulta-completa'
        ),
        'proxy' => [
            'ip' => env('FISCAL_PROXY_IP', ''),
            'port' => env('FISCAL_PROXY_PORT', ''),
            'user' => env('FISCAL_PROXY_USER', ''),
            'password' => env('FISCAL_PROXY_PASSWORD', ''),
        ],
    ],


    'ncm_classif' => [
        'url' => env('NCM_CLASSIF_URL', 'https://portalunico.siscomex.gov.br/classif/api/publico/nomenclatura/download/json'),
    ],


    'nuvemshop' => [
        'client_id' => env('NUVEMSHOP_CLIENT_ID'),
        'client_secret' => env('NUVEMSHOP_CLIENT_SECRET'),
        'redirect_uri' => env('NUVEMSHOP_REDIRECT_URI'),
        'user_agent' => env('NUVEMSHOP_USER_AGENT', 'IntelbrasTech ERP (suporte@intelbrastech.com.br)'),
    ],

];
