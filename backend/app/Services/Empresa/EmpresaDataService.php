<?php

namespace App\Services\Empresa;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmpresaDataService
{
    public function consultarCnpj(string $cnpj): array
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14) {
            throw new RuntimeException('Informe um CNPJ com 14 dígitos.');
        }

        $tentativas = [
            fn () => $this->consultarCnpjWs($cnpj),
            fn () => $this->consultarBrasilApi($cnpj),
            fn () => $this->consultarCnpja($cnpj),
        ];

        $erros = [];

        foreach ($tentativas as $tentativa) {
            try {
                $resultado = $tentativa();

                if ($resultado) {
                    return $resultado;
                }
            } catch (\Throwable $exception) {
                $erros[] = $exception->getMessage();
            }
        }

        throw new RuntimeException(
            'Não foi possível consultar os dados públicos da Receita Federal. ' .
            implode(' | ', array_filter($erros))
        );
    }

    public function consultarCep(string $cep): array
    {
        $cep = preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            throw new RuntimeException('Informe um CEP com 8 dígitos.');
        }

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->retry(1, 500)
                ->get("https://viacep.com.br/ws/{$cep}/json/");

            if ($response->successful() && !$response->json('erro')) {
                $data = $response->json();

                return [
                    'fonte' => 'ViaCEP',
                    'cep' => $cep,
                    'logradouro' => $data['logradouro'] ?? null,
                    'complemento' => $data['complemento'] ?? null,
                    'bairro' => $data['bairro'] ?? null,
                    'cidade' => $data['localidade'] ?? null,
                    'estado' => $data['uf'] ?? null,
                    'ibge' => $data['ibge'] ?? null,
                ];
            }
        } catch (\Throwable) {
            // Continua no fallback.
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->retry(1, 500)
            ->get("https://brasilapi.com.br/api/cep/v2/{$cep}");

        if ($response->failed()) {
            throw new RuntimeException('CEP não encontrado.');
        }

        $data = $response->json();

        return [
            'fonte' => 'BrasilAPI CEP',
            'cep' => $cep,
            'logradouro' => $data['street'] ?? null,
            'complemento' => null,
            'bairro' => $data['neighborhood'] ?? null,
            'cidade' => $data['city'] ?? null,
            'estado' => $data['state'] ?? null,
            'ibge' => null,
        ];
    }

    private function consultarCnpjWs(string $cnpj): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->retry(1, 700)
            ->get("https://publica.cnpj.ws/cnpj/{$cnpj}");

        if ($response->failed()) {
            throw new RuntimeException("CNPJ.ws retornou HTTP {$response->status()}.");
        }

        $data = $response->json();
        $estab = $data['estabelecimento'] ?? [];
        $atividade = $estab['atividade_principal'] ?? [];

        return [
            'fonte' => 'CNPJ.ws — dados públicos da RFB',
            'cnpj' => $cnpj,
            'razao_social' => $data['razao_social'] ?? null,
            'nome_fantasia' => $estab['nome_fantasia'] ?? null,
            'ie' => null,
            'cnae' => $atividade['id'] ?? null,
            'atividade_principal' => $atividade['descricao'] ?? null,
            'porte_empresa' => $data['porte']['descricao'] ?? null,
            'cep' => preg_replace('/\D/', '', (string) ($estab['cep'] ?? '')),
            'logradouro' => trim(implode(' ', array_filter([
                $estab['tipo_logradouro'] ?? null,
                $estab['logradouro'] ?? null,
            ]))),
            'numero' => $estab['numero'] ?? null,
            'complemento' => $estab['complemento'] ?? null,
            'bairro' => $estab['bairro'] ?? null,
            'cidade' => $estab['cidade']['nome'] ?? null,
            'estado' => $estab['estado']['sigla'] ?? null,
            'telefone' => $this->telefone(
                $estab['ddd1'] ?? null,
                $estab['telefone1'] ?? null
            ),
            'email' => $estab['email'] ?? null,
        ];
    }

    private function consultarBrasilApi(string $cnpj): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->retry(1, 700)
            ->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");

        if ($response->failed()) {
            throw new RuntimeException("BrasilAPI retornou HTTP {$response->status()}.");
        }

        $data = $response->json();

        return [
            'fonte' => 'BrasilAPI — dados públicos da RFB',
            'cnpj' => $cnpj,
            'razao_social' => $data['razao_social'] ?? null,
            'nome_fantasia' => $data['nome_fantasia'] ?? null,
            'ie' => null,
            'cnae' => $data['cnae_fiscal'] ?? null,
            'atividade_principal' => $data['cnae_fiscal_descricao'] ?? null,
            'porte_empresa' => $data['porte'] ?? null,
            'cep' => preg_replace('/\D/', '', (string) ($data['cep'] ?? '')),
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['municipio'] ?? null,
            'estado' => $data['uf'] ?? null,
            'telefone' => $this->telefone(
                $data['ddd_telefone_1'] ?? null,
                $data['telefone_1'] ?? null
            ),
            'email' => $data['email'] ?? null,
        ];
    }

    private function consultarCnpja(string $cnpj): array
    {
        $response = Http::acceptJson()
            ->timeout(20)
            ->retry(1, 700)
            ->get("https://open.cnpja.com/office/{$cnpj}");

        if ($response->failed()) {
            throw new RuntimeException("CNPJá retornou HTTP {$response->status()}.");
        }

        $data = $response->json();
        $company = $data['company'] ?? [];
        $main = $data['mainActivity'] ?? [];

        return [
            'fonte' => 'CNPJá Open — dados públicos da RFB',
            'cnpj' => $cnpj,
            'razao_social' => $company['name'] ?? null,
            'nome_fantasia' => $data['alias'] ?? null,
            'ie' => null,
            'cnae' => $main['id'] ?? null,
            'atividade_principal' => $main['text'] ?? null,
            'porte_empresa' => $company['size']['text'] ?? null,
            'cep' => preg_replace('/\D/', '', (string) ($data['address']['zip'] ?? '')),
            'logradouro' => $data['address']['street'] ?? null,
            'numero' => $data['address']['number'] ?? null,
            'complemento' => $data['address']['details'] ?? null,
            'bairro' => $data['address']['district'] ?? null,
            'cidade' => $data['address']['city'] ?? null,
            'estado' => $data['address']['state'] ?? null,
            'telefone' => $data['phones'][0]['number'] ?? null,
            'email' => $data['emails'][0]['address'] ?? null,
        ];
    }

    private function telefone(mixed $ddd, mixed $numero): ?string
    {
        $valor = preg_replace('/\D/', '', (string) $ddd . (string) $numero);

        return $valor !== '' ? $valor : null;
    }
}
