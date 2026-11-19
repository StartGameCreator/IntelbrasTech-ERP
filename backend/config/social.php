<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'auto_register' => (bool) env('SOCIAL_LOGIN_AUTO_REGISTER', false),

    'google' => [
        'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_OAUTH_REDIRECT_URI', env('APP_URL').'/api/v1/auth/social/google/callback'),
        /*
         * Login Google com permissões corporativas.
         *
         * Calendar permite criar e gerenciar reuniões/agendamentos.
         * business.manage permite conectar o Google Business Profile.
         */
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/business.manage',
        ],
    ],
    'microsoft' => [
        'client_id' => env('MICROSOFT_OAUTH_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_OAUTH_CLIENT_SECRET'),
        'tenant' => env('MICROSOFT_OAUTH_TENANT', 'common'),
        'redirect' => env('MICROSOFT_OAUTH_REDIRECT_URI', env('APP_URL').'/api/v1/auth/social/microsoft/callback'),
        'scopes' => ['openid', 'profile', 'email', 'offline_access', 'User.Read', 'Calendars.ReadWrite', 'OnlineMeetings.ReadWrite'],
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_OAUTH_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_OAUTH_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_OAUTH_REDIRECT_URI', env('APP_URL').'/api/v1/auth/social/facebook/callback'),
        'scopes' => ['email', 'public_profile'],
    ],
    'apple' => [
        'client_id' => env('APPLE_OAUTH_CLIENT_ID'),
        'team_id' => env('APPLE_OAUTH_TEAM_ID'),
        'key_id' => env('APPLE_OAUTH_KEY_ID'),
        'private_key' => env('APPLE_OAUTH_PRIVATE_KEY'),
        'private_key_path' => env('APPLE_OAUTH_PRIVATE_KEY_PATH'),
        'redirect' => env('APPLE_OAUTH_REDIRECT_URI', env('APP_URL').'/api/v1/auth/social/apple/callback'),
        'scopes' => ['name', 'email'],
    ],
];
