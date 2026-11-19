<?php

return [

    // Credenciais da aplicação (App do Mercado Livre / Shopee Open Platform).
    // Usadas como fallback global; cada Integracao salva seu próprio token
    // de acesso (OAuth) na coluna `credenciais` depois de conectada.
    'mercadolivre' => [
        'app_id' => env('MERCADOLIVRE_CLIENT_ID'),
        'secret' => env('MERCADOLIVRE_CLIENT_SECRET'),
    ],

    'shopee' => [
        'partner_id'  => env('SHOPEE_PARTNER_ID'),
        'partner_key' => env('SHOPEE_PARTNER_KEY'),
        'base_url'    => env('SHOPEE_BASE_URL', 'https://partner.shopeemobile.com'),
    ],

];
