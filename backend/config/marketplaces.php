<?php
return [
    'frontend_url' => env('FRONTEND_URL','http://localhost:5173'),
    'mercado_livre' => [
        'client_id' => env('MERCADO_LIVRE_CLIENT_ID'),
        'client_secret' => env('MERCADO_LIVRE_CLIENT_SECRET'),
        'redirect_uri' => env('MERCADO_LIVRE_REDIRECT_URI','http://127.0.0.1:8033/api/v1/marketplace/mercado-livre/callback'),
        'authorize_url' => env('MERCADO_LIVRE_AUTHORIZE_URL','https://auth.mercadolivre.com.br/authorization'),
        'token_url' => env('MERCADO_LIVRE_TOKEN_URL','https://api.mercadolibre.com/oauth/token'),
        'api_url' => env('MERCADO_LIVRE_API_URL','https://api.mercadolibre.com'),
    ],
];
