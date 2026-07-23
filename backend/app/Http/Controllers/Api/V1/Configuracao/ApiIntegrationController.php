<?php

namespace App\Http\Controllers\Api\V1\Configuracao;

use App\Http\Controllers\Controller;
use App\Models\ApiIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ApiIntegrationController extends Controller
{
    private array $catalog = [
        'google_places' => [
            'name' => 'Google Places',
            'category' => 'google',
            'credentials' => ['api_key'],
            'settings' => [
                'language' => 'pt-BR',
                'region' => 'BR',
                'timeout' => 20,
                'share_google_key' => true,
            ],
        ],
        'google_maps' => [
            'name' => 'Google Maps JavaScript',
            'category' => 'google',
            'credentials' => ['api_key'],
            'settings' => ['map_id' => 'DEMO_MAP_ID'],
        ],
        'google_geocoding' => [
            'name' => 'Google Geocoding',
            'category' => 'google',
            'credentials' => ['api_key'],
            'settings' => ['language' => 'pt-BR', 'region' => 'BR', 'timeout' => 20],
        ],
        'cnpjws' => [
            'name' => 'CNPJ.ws',
            'category' => 'cnpj',
            'credentials' => ['token'],
            'settings' => [
                'base_url' => 'https://publica.cnpj.ws/cnpj',
                'timeout' => 20,
                'advanced_search' => false,
            ],
        ],
        'cnpja' => [
            'name' => 'CNPJá',
            'category' => 'cnpj',
            'credentials' => ['api_key'],
            'settings' => [
                'base_url' => 'https://open.cnpja.com/office',
                'timeout' => 20,
                'advanced_search' => false,
            ],
        ],
        'opencnpj' => [
            'name' => 'OpenCNPJ',
            'category' => 'cnpj',
            'credentials' => ['api_key'],
            'settings' => [
                'base_url' => 'https://api.opencnpj.org',
                'timeout' => 20,
                'advanced_search' => false,
            ],
        ],
        'custom_cnpj' => [
            'name' => 'Outro fornecedor CNPJ',
            'category' => 'cnpj',
            'credentials' => ['api_key', 'token'],
            'settings' => [
                'base_url' => '',
                'timeout' => 20,
                'advanced_search' => true,
            ],
        ],
        'social_google' => [
            'name' => 'Entrar com Google',
            'category' => 'social',
            'credentials' => ['client_id', 'client_secret'],
            'settings' => [
                'redirect_uri' => 'http://127.0.0.1:8033/api/v1/auth/social/google/callback',
                'sync_calendar' => true,
                'sync_business' => true,
            ],
        ],
        'social_microsoft' => [
            'name' => 'Entrar com Microsoft',
            'category' => 'social',
            'credentials' => ['client_id', 'client_secret'],
            'settings' => [
                'tenant' => 'common',
                'redirect_uri' => 'http://127.0.0.1:8033/api/v1/auth/social/microsoft/callback',
                'sync_calendar' => true,
            ],
        ],
        'social_apple' => [
            'name' => 'Entrar com Apple',
            'category' => 'social',
            'credentials' => ['client_id', 'team_id', 'key_id', 'private_key'],
            'settings' => [
                'redirect_uri' => 'http://127.0.0.1:8033/api/v1/auth/social/apple/callback',
            ],
        ],
        'social_facebook' => [
            'name' => 'Entrar com Facebook',
            'category' => 'social',
            'credentials' => ['client_id', 'client_secret'],
            'settings' => [
                'redirect_uri' => 'http://127.0.0.1:8033/api/v1/auth/social/facebook/callback',
            ],
        ],
    ];

    private function companyId(): ?int
    {
        return auth()->user()?->empresa_id;
    }

    public function index(): JsonResponse
    {
        $this->authorize('integracoes.view');

        $saved = ApiIntegration::where('empresa_id', $this->companyId())
            ->get()
            ->keyBy('provider');

        $data = collect($this->catalog)->map(function (array $definition, string $provider) use ($saved) {
            $integration = $saved->get($provider);

            return [
                'provider' => $provider,
                'name' => $definition['name'],
                'category' => $definition['category'],
                'credential_fields' => $definition['credentials'],
                'default_settings' => $definition['settings'],
                'configured' => (bool) $integration,
                'credentials' => $integration?->maskedCredentials() ?? [],
                'settings' => $integration?->settings ?? $definition['settings'],
                'enabled' => $integration?->enabled ?? false,
                'is_primary' => $integration?->is_primary ?? false,
                'is_fallback' => $integration?->is_fallback ?? false,
                'status' => $integration?->status ?? 'not_configured',
                'last_tested_at' => $integration?->last_tested_at,
                'last_test_message' => $integration?->last_test_message,
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function save(Request $request, string $provider): JsonResponse
    {
        $this->authorize('integracoes.edit');
        abort_unless(isset($this->catalog[$provider]), 404, 'Fornecedor não cadastrado.');

        $data = $request->validate([
            'credentials' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'enabled' => ['required', 'boolean'],
            'is_primary' => ['nullable', 'boolean'],
            'is_fallback' => ['nullable', 'boolean'],
        ]);

        $definition = $this->catalog[$provider];

        $integration = ApiIntegration::firstOrNew([
            'empresa_id' => $this->companyId(),
            'provider' => $provider,
        ]);

        $settings = array_merge(
            $definition['settings'],
            $data['settings'] ?? []
        );

        $integration->fill([
            'name' => $definition['name'],
            'category' => $definition['category'],
            'settings' => $settings,
            'enabled' => $data['enabled'],
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'is_fallback' => (bool) ($data['is_fallback'] ?? false),
            'status' => $integration->status ?: 'not_tested',
        ]);

        $incoming = collect($data['credentials'] ?? [])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => filled($value))
            ->all();

        if ($incoming) {
            $integration->setCredentials(
                array_merge($integration->getCredentials(), $incoming)
            );
        }

        $integration->save();

        if ($integration->is_primary) {
            ApiIntegration::where('empresa_id', $this->companyId())
                ->where('category', $definition['category'])
                ->where('id', '!=', $integration->id)
                ->update(['is_primary' => false]);
        }

        $sharedProviders = [];

        if (
            $provider === 'google_places'
            && (bool) ($settings['share_google_key'] ?? true)
        ) {
            $apiKey = $integration->getCredentials()['api_key'] ?? null;

            if (filled($apiKey)) {
                $sharedProviders = $this->shareGoogleKey(
                    (string) $apiKey,
                    (bool) $data['enabled']
                );
            }
        }

        return response()->json([
            'message' => $sharedProviders
                ? 'Configuração salva. A mesma chave foi aplicada ao Maps JavaScript e ao Geocoding.'
                : 'Configuração salva com segurança.',
            'shared_providers' => $sharedProviders,
            'data' => $this->serialize($integration),
        ]);
    }

    public function test(string $provider): JsonResponse
    {
        $this->authorize('integracoes.view');
        abort_unless(isset($this->catalog[$provider]), 404, 'Fornecedor não cadastrado.');

        $integration = ApiIntegration::where('empresa_id', $this->companyId())
            ->where('provider', $provider)
            ->firstOrFail();

        [$ok, $message] = $this->performTest($provider, $integration);

        $integration->update([
            'status' => $ok ? 'connected' : 'error',
            'last_tested_at' => now(),
            'last_test_message' => $message,
        ]);

        return response()->json([
            'ok' => $ok,
            'message' => $message,
            'data' => $this->serialize($integration->fresh()),
        ], $ok ? 200 : 422);
    }

    private function shareGoogleKey(string $apiKey, bool $enabled): array
    {
        $shared = [];

        foreach (['google_maps', 'google_geocoding'] as $provider) {
            $definition = $this->catalog[$provider];

            $integration = ApiIntegration::firstOrNew([
                'empresa_id' => $this->companyId(),
                'provider' => $provider,
            ]);

            $integration->fill([
                'name' => $definition['name'],
                'category' => $definition['category'],
                'settings' => array_merge(
                    $definition['settings'],
                    $integration->settings ?? []
                ),
                'enabled' => $enabled,
                'status' => 'not_tested',
            ]);

            $credentials = $integration->getCredentials();
            $credentials['api_key'] = $apiKey;
            $integration->setCredentials($credentials);
            $integration->save();

            $shared[] = $provider;
        }

        return $shared;
    }

    private function performTest(string $provider, ApiIntegration $integration): array
    {
        $credentials = $integration->getCredentials();
        $settings = $integration->settings ?: [];
        $timeout = (int) ($settings['timeout'] ?? 20);

        if (str_starts_with($provider, 'social_')) {
            $missing = array_filter(
                $this->catalog[$provider]['credentials'],
                fn ($key) => blank($credentials[$key] ?? null)
            );

            return $missing
                ? [false, 'Campos obrigatórios ausentes: ' . implode(', ', $missing)]
                : [true, 'Configuração OAuth validada. Use o botão de conexão para concluir o teste real.'];
        }

        try {
            if ($provider === 'google_places') {
                if (blank($credentials['api_key'] ?? null)) {
                    return [false, 'Informe a chave da Google Places API.'];
                }

                $response = Http::timeout($timeout)
                    ->acceptJson()
                    ->withHeaders([
                        'X-Goog-Api-Key' => $credentials['api_key'],
                        'X-Goog-FieldMask' => 'places.id,places.displayName',
                    ])
                    ->post(
                        'https://places.googleapis.com/v1/places:searchText',
                        [
                            'textQuery' => 'Intelbras Brasil',
                            'languageCode' => $settings['language'] ?? 'pt-BR',
                            'regionCode' => $settings['region'] ?? 'BR',
                            'pageSize' => 1,
                        ]
                    );

                if ($response->successful()) {
                    return [true, 'Google Places está ativa e respondeu corretamente.'];
                }

                return [
                    false,
                    $this->friendlyGoogleMessage(
                        $response->json('error.message'),
                        $response->status(),
                        'Places API (New)'
                    ),
                ];
            }

            if (in_array($provider, ['google_maps', 'google_geocoding'], true)) {
                if (blank($credentials['api_key'] ?? null)) {
                    return [false, 'Informe a chave Google.'];
                }

                $response = Http::timeout($timeout)
                    ->get(
                        'https://maps.googleapis.com/maps/api/geocode/json',
                        [
                            'address' => 'Brasilia, DF',
                            'key' => $credentials['api_key'],
                        ]
                    );

                $status = $response->json('status');

                if ($response->successful() && $status === 'OK') {
                    return [
                        true,
                        $provider === 'google_maps'
                            ? 'A chave Google está válida para os serviços de mapas.'
                            : 'Google Geocoding está ativa e respondeu corretamente.',
                    ];
                }

                return [
                    false,
                    $this->friendlyGoogleMessage(
                        $response->json('error_message') ?: $status,
                        $response->status(),
                        $provider === 'google_maps'
                            ? 'Maps JavaScript API'
                            : 'Geocoding API'
                    ),
                ];
            }

            if (in_array($provider, ['cnpjws', 'cnpja', 'opencnpj', 'custom_cnpj'], true)) {
                $baseUrl = rtrim((string) ($settings['base_url'] ?? ''), '/');

                if (!$baseUrl) {
                    return [false, 'Informe a URL base.'];
                }

                $query = Http::timeout($timeout)->acceptJson();

                if (filled($credentials['token'] ?? null)) {
                    $query = $query->withToken($credentials['token']);
                }

                if (filled($credentials['api_key'] ?? null)) {
                    $query = $query->withHeaders([
                        'X-API-Key' => $credentials['api_key'],
                    ]);
                }

                $response = $query->get($baseUrl . '/33000167000101');

                return [
                    $response->successful(),
                    $response->successful()
                        ? 'Fornecedor CNPJ respondeu corretamente.'
                        : 'O fornecedor CNPJ respondeu com HTTP ' . $response->status() . '.',
                ];
            }
        } catch (\Throwable $exception) {
            report($exception);

            return [
                false,
                'Não foi possível conectar ao serviço. Verifique internet, certificado HTTPS e tente novamente.',
            ];
        }

        return [false, 'Teste ainda não implementado para esta integração.'];
    }

    private function friendlyGoogleMessage(
        mixed $message,
        int $httpStatus,
        string $service
    ): string {
        $text = trim((string) $message);
        $lower = mb_strtolower($text);

        if (
            str_contains($lower, 'has not been used')
            || str_contains($lower, 'is disabled')
            || str_contains($lower, 'access not configured')
        ) {
            return "{$service} está desativada neste projeto do Google Cloud. "
                . 'Ative a API no Console do Google, aguarde alguns minutos e valide novamente.';
        }

        if (
            str_contains($lower, 'api key not valid')
            || str_contains($lower, 'invalid key')
            || str_contains($lower, 'api_key_invalid')
        ) {
            return 'A chave informada é inválida. Gere ou copie novamente a chave no Google Cloud.';
        }

        if (
            str_contains($lower, 'billing')
            || str_contains($lower, 'billingnotenabled')
        ) {
            return 'O faturamento não está habilitado para este projeto do Google Cloud.';
        }

        if (
            str_contains($lower, 'referer')
            || str_contains($lower, 'ip address')
            || str_contains($lower, 'request is blocked')
            || str_contains($lower, 'permission_denied')
        ) {
            return 'A chave foi reconhecida, mas as restrições não permitem esta chamada. '
                . 'Revise as restrições de aplicativo e de API no Google Cloud.';
        }

        if ($httpStatus === 429) {
            return 'A cota da API foi excedida temporariamente. Aguarde e tente novamente.';
        }

        return $text !== ''
            ? "{$service} retornou: {$text}"
            : "{$service} não respondeu corretamente (HTTP {$httpStatus}).";
    }

    private function serialize(ApiIntegration $integration): array
    {
        return [
            'provider' => $integration->provider,
            'name' => $integration->name,
            'category' => $integration->category,
            'credentials' => $integration->maskedCredentials(),
            'settings' => $integration->settings,
            'enabled' => $integration->enabled,
            'is_primary' => $integration->is_primary,
            'is_fallback' => $integration->is_fallback,
            'status' => $integration->status,
            'last_tested_at' => $integration->last_tested_at,
            'last_test_message' => $integration->last_test_message,
        ];
    }
}
