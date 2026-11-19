<?php

return [
    'provider' => env('PRODUCT_AI_PROVIDER', 'local'),
    'timeout' => (int) env('PRODUCT_AI_TIMEOUT', 60),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('PRODUCT_AI_OPENAI_MODEL', 'gpt-4.1-mini'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('PRODUCT_AI_GEMINI_MODEL', 'gemini-2.0-flash'),
    ],
];
