<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\{ApiIntegration, Lead, Pipeline, PipelineEtapa};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoogleBusinessController extends Controller
{
    private const SEARCH_URL = 'https://places.googleapis.com/v1/places:searchText';

    public function status(): JsonResponse
    {
        $this->authorize('crm.view');

        $configuration = $this->placesConfiguration();
        $configured = filled($configuration['api_key']);

        return response()->json([
            'configured' => $configured,
            'provider' => 'Google Places API (New)',
            'source' => $configuration['source'],
            'message' => $configured
                ? 'Integração pronta para buscar empresas no Google.'
                : 'Cadastre a Chave da API em Configurações > APIs > Google Places.',
        ]);
    }

    public function pesquisar(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        $dados = $request->validate([
            'termo' => ['required', 'string', 'min:2', 'max:180'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'size:2'],
            'min_avaliacao' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'min_avaliacoes' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'somente_abertos' => ['nullable', 'boolean'],
            'somente_com_site' => ['nullable', 'boolean'],
            'somente_com_fone' => ['nullable', 'boolean'],
            'pagina_token' => ['nullable', 'string', 'max:2048'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $configuration = $this->placesConfiguration();
        $apiKey = $configuration['api_key'];

        if ($apiKey === '') {
            return response()->json([
                'message' => 'Google Places API não configurada.',
                'details' => 'Abra Configurações > APIs > Google Places, informe a Chave da API, ative e valide a configuração.',
            ], 503);
        }

        $partes = [trim($dados['termo'])];
        if (! empty($dados['cidade'])) $partes[] = trim($dados['cidade']);
        if (! empty($dados['estado'])) $partes[] = strtoupper($dados['estado']);
        $partes[] = 'Brasil';

        $payload = [
            'textQuery' => implode(', ', $partes),
            'languageCode' => $configuration['language'],
            'regionCode' => $configuration['region'],
            'pageSize' => (int) ($dados['limite'] ?? 20),
        ];
        if (! empty($dados['pagina_token'])) $payload['pageToken'] = $dados['pagina_token'];

        $fieldMask = implode(',', [
            'places.id', 'places.displayName', 'places.formattedAddress',
            'places.addressComponents', 'places.location', 'places.primaryType',
            'places.primaryTypeDisplayName', 'places.businessStatus',
            'places.nationalPhoneNumber', 'places.internationalPhoneNumber',
            'places.websiteUri', 'places.googleMapsUri', 'places.rating',
            'places.userRatingCount', 'places.currentOpeningHours.openNow',
            'nextPageToken',
        ]);

        try {
            $response = Http::timeout($configuration['timeout'])
                ->acceptJson()
                ->withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => $fieldMask,
                ])
                ->post(self::SEARCH_URL, $payload);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Nao foi possivel consultar o Google Places.',
                    'google_status' => $response->status(),
                    'google_error' => $response->json('error.message') ?: $response->body(),
                ], $response->status() >= 500 ? 502 : 422);
            }

            $places = collect($response->json('places', []))
                ->map(fn (array $place) => $this->normalizePlace($place))
                ->filter(function (array $empresa) use ($dados): bool {
                    if (array_key_exists('min_avaliacao', $dados) && ($empresa['avaliacao'] ?? 0) < (float) $dados['min_avaliacao']) return false;
                    if (array_key_exists('min_avaliacoes', $dados) && ($empresa['total_avaliacoes'] ?? 0) < (int) $dados['min_avaliacoes']) return false;
                    if (! empty($dados['somente_abertos']) && $empresa['aberto_agora'] !== true) return false;
                    if (! empty($dados['somente_com_site']) && blank($empresa['site'])) return false;
                    if (! empty($dados['somente_com_fone']) && blank($empresa['telefone'])) return false;
                    return true;
                })
                ->values();

            return response()->json([
                'data' => $places,
                'total' => $places->count(),
                'next_page_token' => $response->json('nextPageToken'),
                'consulta' => $payload['textQuery'],
                'source' => 'Google Places API (New)',
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => 'Falha de comunicacao com o Google Places.',
                'details' => app()->isLocal() ? $e->getMessage() : null,
            ], 502);
        }
    }

    public function importarLead(Request $request): JsonResponse
    {
        $this->authorize('crm.create');

        $dados = $request->validate([
            'google_place_id' => ['required', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:200'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'site' => ['nullable', 'url', 'max:500'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'categoria' => ['nullable', 'string', 'max:150'],
            'avaliacao' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'total_avaliacoes' => ['nullable', 'integer', 'min:0'],
            'maps_url' => ['nullable', 'url', 'max:1000'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $user = $request->user();
        $empresaId = $user?->empresa_id;
        if (! $empresaId) {
            return response()->json(['message' => 'Usuario sem empresa vinculada.'], 422);
        }

        $tagPlace = 'google_place:' . $dados['google_place_id'];
        $existente = Lead::where('empresa_id', $empresaId)
            ->where('tags', 'like', '%' . $tagPlace . '%')
            ->first();

        if ($existente) {
            return response()->json(['message' => 'Esta empresa ja foi importada para o CRM.', 'lead' => $existente], 409);
        }

        $pipeline = Pipeline::where('empresa_id', $empresaId)->where('ativo', true)->first();
        $etapaId = $pipeline
            ? PipelineEtapa::where('pipeline_id', $pipeline->id)->orderBy('ordem')->value('id')
            : null;

        $tags = array_filter([
            'google-business', $tagPlace, $dados['categoria'] ?? null,
            isset($dados['avaliacao']) ? 'avaliacao:' . $dados['avaliacao'] : null,
            ! empty($dados['estado']) ? 'uf:' . strtoupper((string) $dados['estado']) : null,
        ]);

        $lead = Lead::create([
            'empresa_id' => $empresaId,
            'pipeline_id' => $pipeline?->id,
            'etapa_id' => $etapaId,
            'responsavel_id' => $user->id,
            'nome' => $dados['nome'],
            'empresa_nome' => $dados['nome'],
            'telefone' => $dados['telefone'] ?? null,
            'origem' => 'google_business',
            'titulo' => 'Prospeccao Google: ' . Str::limit($dados['nome'], 150),
            'status' => Lead::STATUS_ABERTO,
            'probabilidade' => 10,
            'tags' => implode(',', $tags),
        ]);

        $lead = app(\App\Services\Crm\CrmLeadIntakeService::class)
            ->registrar($lead, $user->id, 'Lead importado do Google Business');

        return response()->json([
            'message' => 'Empresa importada como lead com sucesso.',
            'lead' => $lead->fresh(['etapa', 'responsavel']),
        ], 201);
    }


    private function placesConfiguration(): array
    {
        $empresaId = request()->user()?->empresa_id;

        $integration = $empresaId
            ? ApiIntegration::query()
                ->where('empresa_id', $empresaId)
                ->where('provider', 'google_places')
                ->where('enabled', true)
                ->first()
            : null;

        $credentials = $integration?->getCredentials() ?? [];
        $settings = $integration?->settings ?? [];

        $databaseKey = trim((string) ($credentials['api_key'] ?? ''));
        $environmentKey = trim((string) config('google_business.places_api_key', ''));

        return [
            'api_key' => $databaseKey !== '' ? $databaseKey : $environmentKey,
            'language' => (string) ($settings['language']
                ?? config('google_business.language_code', 'pt-BR')),
            'region' => strtoupper((string) ($settings['region']
                ?? config('google_business.region_code', 'BR'))),
            'timeout' => max(5, min(60, (int) ($settings['timeout']
                ?? config('google_business.timeout', 20)))),
            'source' => $databaseKey !== ''
                ? 'Central de APIs'
                : ($environmentKey !== '' ? 'backend/.env' : 'não configurada'),
        ];
    }

    private function normalizePlace(array $place): array
    {
        $components = $place['addressComponents'] ?? [];
        $find = static function (string $type) use ($components): ?string {
            foreach ($components as $component) {
                if (in_array($type, $component['types'] ?? [], true)) {
                    return $component['longText'] ?? $component['shortText'] ?? null;
                }
            }
            return null;
        };

        return [
            'google_place_id' => $place['id'] ?? null,
            'nome' => data_get($place, 'displayName.text'),
            'endereco' => $place['formattedAddress'] ?? null,
            'cidade' => $find('administrative_area_level_2') ?: $find('locality'),
            'estado' => $find('administrative_area_level_1'),
            'cep' => $find('postal_code'),
            'telefone' => $place['nationalPhoneNumber'] ?? $place['internationalPhoneNumber'] ?? null,
            'site' => $place['websiteUri'] ?? null,
            'maps_url' => $place['googleMapsUri'] ?? null,
            'avaliacao' => isset($place['rating']) ? (float) $place['rating'] : null,
            'total_avaliacoes' => (int) ($place['userRatingCount'] ?? 0),
            'categoria' => data_get($place, 'primaryTypeDisplayName.text') ?: ($place['primaryType'] ?? null),
            'tipo_google' => $place['primaryType'] ?? null,
            'situacao' => $place['businessStatus'] ?? null,
            'aberto_agora' => data_get($place, 'currentOpeningHours.openNow'),
            'latitude' => data_get($place, 'location.latitude'),
            'longitude' => data_get($place, 'location.longitude'),
        ];
    }
}
