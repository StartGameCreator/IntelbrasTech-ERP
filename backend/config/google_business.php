<?php

return [
    'places_api_key' => env('GOOGLE_PLACES_API_KEY'),
    'language_code' => env('GOOGLE_PLACES_LANGUAGE', 'pt-BR'),
    'region_code' => env('GOOGLE_PLACES_REGION', 'BR'),
    'timeout' => (int) env('GOOGLE_PLACES_TIMEOUT', 20),
];
