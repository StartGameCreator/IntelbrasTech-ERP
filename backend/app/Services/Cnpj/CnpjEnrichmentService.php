<?php

namespace App\Services\Cnpj;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CnpjEnrichmentService
{
    public function consultar(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            throw new RuntimeException('CNPJ inválido.');
        }

        $provider = strtolower((string) config('services.cnpj.provider', env('CNPJ_PROVIDER', 'brasilapi')));

        return match ($provider) {
            'serpro' => $this->consultarSerpro($cnpj),
            'brasilapi' => $this->consultarBrasilApi($cnpj),
            default => $this->consultarBrasilApi($cnpj),
        };
    }

    private function consultarBrasilApi(string $cnpj): array
    {
        $response = Http::acceptJson()
            ->timeout((int) env('CNPJ_TIMEOUT', 20))
            ->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

        if ($response->failed()) {
            throw new RuntimeException(
                $response->json('message')
                ?: 'Não foi possível consultar o CNPJ na fonte pública.'
            );
        }

        $data = $response->json();

        return [
            'provider' => 'BrasilAPI / dados públicos da RFB',
            'cnpj' => preg_replace('/\D/', '', (string) ($data['cnpj'] ?? $cnpj)),
            'razao_social' => $data['razao_social'] ?? null,
            'nome_fantasia' => $data['nome_fantasia'] ?? null,
            'situacao_cadastral' => $data['descricao_situacao_cadastral'] ?? null,
            'data_situacao_cadastral' => $data['data_situacao_cadastral'] ?? null,
            'data_inicio_atividade' => $data['data_inicio_atividade'] ?? null,
            'cnae_fiscal' => $data['cnae_fiscal'] ?? null,
            'cnae_fiscal_descricao' => $data['cnae_fiscal_descricao'] ?? null,
            'natureza_juridica' => $data['natureza_juridica'] ?? null,
            'porte' => $data['porte'] ?? null,
            'capital_social' => $data['capital_social'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'municipio' => $data['municipio'] ?? null,
            'uf' => $data['uf'] ?? null,
            'cep' => preg_replace('/\D/', '', (string) ($data['cep'] ?? '')) ?: null,
            'telefone' => $this->primeiroTelefone($data),
            'email' => $data['email'] ?? null,
            'socios' => collect($data['qsa'] ?? [])
                ->map(fn (array $socio) => [
                    'nome' => $socio['nome_socio'] ?? null,
                    'qualificacao' => $socio['qualificacao_socio'] ?? null,
                    'faixa_etaria' => $socio['faixa_etaria'] ?? null,
                ])
                ->filter(fn (array $socio) => filled($socio['nome']))
                ->values()
                ->all(),
            'raw' => $data,
        ];
    }

    private function consultarSerpro(string $cnpj): array
    {
        $tokenUrl = env('SERPRO_CNPJ_TOKEN_URL');
        $baseUrl = rtrim((string) env('SERPRO_CNPJ_BASE_URL'), '/');
        $clientId = env('SERPRO_CNPJ_CLIENT_ID');
        $clientSecret = env('SERPRO_CNPJ_CLIENT_SECRET');

        if (!$tokenUrl || !$baseUrl || !$clientId || !$clientSecret) {
            throw new RuntimeException(
                'A integração SERPRO ainda não está configurada no menu APIs.'
            );
        }

        $tokenResponse = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout((int) env('CNPJ_TIMEOUT', 20))
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
            ]);

        if ($tokenResponse->failed()) {
            throw new RuntimeException('Falha ao autenticar na API SERPRO.');
        }

        $token = $tokenResponse->json('access_token');

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) env('CNPJ_TIMEOUT', 20))
            ->get("{$baseUrl}/{$cnpj}");

        if ($response->failed()) {
            throw new RuntimeException('Falha ao consultar o CNPJ no SERPRO.');
        }

        // O mapeamento exato pode variar conforme o produto contratado.
        // Mantemos fallback para dados públicos até a configuração final do contrato.
        return [
            'provider' => 'SERPRO / Receita Federal',
            'cnpj' => $cnpj,
            'raw' => $response->json(),
        ];
    }

    private function primeiroTelefone(array $data): ?string
    {
        $ddd = preg_replace('/\D/', '', (string) ($data['ddd_telefone_1'] ?? ''));
        $telefone = preg_replace('/\D/', '', (string) ($data['telefone_1'] ?? ''));

        if ($ddd || $telefone) {
            return trim("{$ddd}{$telefone}");
        }

        $alternativos = [
            $data['ddd_telefone_2'] ?? null,
            $data['telefone_2'] ?? null,
        ];

        $valor = preg_replace('/\D/', '', implode('', array_filter($alternativos)));

        return $valor !== '' ? $valor : null;
    }
}
