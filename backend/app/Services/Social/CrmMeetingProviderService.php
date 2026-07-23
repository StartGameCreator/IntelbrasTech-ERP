<?php

namespace App\Services\Social;

use App\Models\ApiIntegration;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CrmMeetingProviderService
{
    public function status(int $usuarioId): array
    {
        $accounts = SocialAccount::query()
            ->where('usuario_id', $usuarioId)
            ->where('ativo', true)
            ->whereIn('provider', ['google', 'microsoft'])
            ->get()
            ->keyBy('provider');

        return [
            'google' => $this->statusItem($accounts->get('google')),
            'microsoft' => $this->statusItem($accounts->get('microsoft')),
        ];
    }

    public function create(SocialAccount $account, array $data): array
    {
        $token = $this->validAccessToken($account);

        return match ($account->provider) {
            'google' => $this->createGoogleMeet($token, $data),
            'microsoft' => $this->createMicrosoftTeams($token, $data),
            default => throw new RuntimeException('Provedor de reunião não suportado.'),
        };
    }

    private function statusItem(?SocialAccount $account): array
    {
        return [
            'connected' => (bool) $account,
            'email' => $account?->email,
            'expires_at' => $account?->expires_at?->toIso8601String(),
            'provider' => $account?->provider,
        ];
    }

    private function validAccessToken(SocialAccount $account): string
    {
        if (!$account->access_token) {
            throw new RuntimeException('A conta conectada não possui token de acesso. Reconecte a conta.');
        }

        if (!$account->expires_at || $account->expires_at->isAfter(now()->addMinute())) {
            return $account->access_token;
        }

        if (!$account->refresh_token) {
            throw new RuntimeException('A sessão do provedor expirou. Reconecte a conta na Central de APIs.');
        }

        $config = $this->providerConfig($account->provider, (int) $account->empresa_id);
        $response = match ($account->provider) {
            'google' => Http::asForm()->timeout(25)->post('https://oauth2.googleapis.com/token', [
                'client_id' => $config['client_id'] ?? null,
                'client_secret' => $config['client_secret'] ?? null,
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ]),
            'microsoft' => Http::asForm()->timeout(25)->post(
                'https://login.microsoftonline.com/'.($config['tenant'] ?? 'common').'/oauth2/v2.0/token',
                [
                    'client_id' => $config['client_id'] ?? null,
                    'client_secret' => $config['client_secret'] ?? null,
                    'refresh_token' => $account->refresh_token,
                    'grant_type' => 'refresh_token',
                    'scope' => implode(' ', $config['scopes'] ?? []),
                ]
            ),
            default => throw new RuntimeException('Provedor inválido.'),
        };

        if ($response->failed()) {
            throw new RuntimeException('Não foi possível renovar a sessão do provedor. Reconecte a conta.');
        }

        $tokens = $response->json();
        $account->update([
            'access_token' => $tokens['access_token'] ?? $account->access_token,
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            'scopes' => isset($tokens['scope']) ? preg_split('/[ ,]+/', $tokens['scope']) : $account->scopes,
        ]);

        return (string) $account->fresh()->access_token;
    }

    private function createGoogleMeet(string $token, array $data): array
    {
        $payload = [
            'summary' => $data['titulo'],
            'description' => $data['descricao'] ?? null,
            'start' => [
                'dateTime' => Carbon::parse($data['inicio'])->toIso8601String(),
                'timeZone' => $data['timezone'],
            ],
            'end' => [
                'dateTime' => Carbon::parse($data['fim'])->toIso8601String(),
                'timeZone' => $data['timezone'],
            ],
            'attendees' => collect($data['participantes'] ?? [])->map(fn ($email) => ['email' => $email])->values()->all(),
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => (string) Str::uuid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all', $payload);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Falha ao criar reunião no Google Meet.');
        }

        $event = $response->json();
        $joinUrl = $event['hangoutLink'] ?? collect($event['conferenceData']['entryPoints'] ?? [])->firstWhere('entryPointType', 'video')['uri'] ?? null;

        return [
            'external_id' => $event['id'] ?? null,
            'join_url' => $joinUrl,
            'event_url' => $event['htmlLink'] ?? $joinUrl,
            'raw' => $event,
        ];
    }

    private function createMicrosoftTeams(string $token, array $data): array
    {
        $payload = [
            'subject' => $data['titulo'],
            'body' => [
                'contentType' => 'HTML',
                'content' => nl2br(e($data['descricao'] ?? 'Reunião criada pelo IntelbrasTech ERP.')),
            ],
            'start' => [
                'dateTime' => Carbon::parse($data['inicio'])->format('Y-m-d\TH:i:s'),
                'timeZone' => $data['timezone'],
            ],
            'end' => [
                'dateTime' => Carbon::parse($data['fim'])->format('Y-m-d\TH:i:s'),
                'timeZone' => $data['timezone'],
            ],
            'attendees' => collect($data['participantes'] ?? [])->map(fn ($email) => [
                'emailAddress' => ['address' => $email, 'name' => $email],
                'type' => 'required',
            ])->values()->all(),
            'isOnlineMeeting' => true,
            'onlineMeetingProvider' => 'teamsForBusiness',
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post('https://graph.microsoft.com/v1.0/me/events', $payload);

        if ($response->failed()) {
            throw new RuntimeException($response->json('error.message') ?: 'Falha ao criar reunião no Microsoft Teams.');
        }

        $event = $response->json();
        return [
            'external_id' => $event['id'] ?? null,
            'join_url' => data_get($event, 'onlineMeeting.joinUrl'),
            'event_url' => $event['webLink'] ?? data_get($event, 'onlineMeeting.joinUrl'),
            'raw' => $event,
        ];
    }

    private function providerConfig(string $provider, int $empresaId): array
    {
        $base = config("social.$provider") ?: [];
        $integration = ApiIntegration::query()
            ->where('empresa_id', $empresaId)
            ->where('provider', 'social_'.$provider)
            ->where('enabled', true)
            ->first();

        if (!$integration) {
            return $base;
        }

        $credentials = $integration->getCredentials();
        $settings = $integration->settings ?: [];

        return array_merge($base, [
            'client_id' => $credentials['client_id'] ?? ($base['client_id'] ?? null),
            'client_secret' => $credentials['client_secret'] ?? ($base['client_secret'] ?? null),
            'tenant' => $settings['tenant'] ?? ($base['tenant'] ?? 'common'),
        ]);
    }
}
