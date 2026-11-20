<?php

return [
    'empresa_id' => env('PORTAL_EMPRESA_ID'),
    'loja_url' => env('PORTAL_LOJA_URL', 'https://www.loja.intelbrastech.com.br'),
    'whatsapp' => env('PORTAL_WHATSAPP'),
    'instagram_url' => env('PORTAL_INSTAGRAM_URL'),
    'facebook_url' => env('PORTAL_FACEBOOK_URL'),
    'autorizacao_intelbras_confirmada' => env('PORTAL_AUTORIZACAO_INTELBRAS', false),
];
