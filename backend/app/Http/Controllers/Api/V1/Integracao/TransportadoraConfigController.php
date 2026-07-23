<?php

namespace App\Http\Controllers\Api\V1\Integracao;

use App\Http\Controllers\Controller;
use App\Models\Transportadora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransportadoraConfigController extends Controller
{
    private array $transportadoras = [
        'correios' => 'Correios',
        'jadlog' => 'JadLog',
        'azul_cargo_express' => 'Azul Cargo Express',
        'loggi' => 'Loggi',
        'olist_pax' => 'Olist Pax',
        'sequoia' => 'Sequoia',
        'andorinha_express' => 'Andorinha Express',
        'asap_log' => 'ASAP Log',
        'arena_cargo' => 'Arena Cargo',
        'uber_envios' => 'Uber Envios',
    ];

    public function catalogo(): JsonResponse
    {
        $this->authorize('integracoes.view');

        return response()->json([
            'data' => collect($this->transportadoras)->map(fn ($nome, $tipo) => [
                'api_tipo' => $tipo,
                'nome' => $nome,
                'campos' => $this->camposPadraoTransportadora($tipo),
            ])->values(),
        ]);
    }

    public function index(): JsonResponse
    {
        $this->authorize('integracoes.view');

        return response()->json([
            'data' => Transportadora::orderBy('nome')->get(),
        ]);
    }

    public function salvar(Request $request, string $apiTipo): JsonResponse
    {
        $this->authorize('integracoes.edit');
        abort_unless(isset($this->transportadoras[$apiTipo]), 404, 'Transportadora não cadastrada no catálogo.');

        $data = $request->validate([
            'nome' => ['nullable', 'string', 'max:150'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'ie' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'api_usuario' => ['nullable', 'string', 'max:200'],
            'api_senha' => ['nullable', 'string', 'max:200'],
            'api_token' => ['nullable', 'string'],
            'api_contrato' => ['nullable', 'string', 'max:50'],
            'ativo' => ['nullable', 'boolean'],
        ]);

        $transportadora = Transportadora::updateOrCreate(
            ['empresa_id' => auth()->user()->empresa_id, 'api_tipo' => $apiTipo],
            array_merge($data, [
                'nome' => $data['nome'] ?? $this->transportadoras[$apiTipo],
                'ativo' => $data['ativo'] ?? true,
            ])
        );

        return response()->json([
            'message' => 'Configuração da transportadora salva com sucesso.',
            'data' => $transportadora,
        ]);
    }

    public function testar(string $apiTipo): JsonResponse
    {
        $this->authorize('integracoes.view');
        abort_unless(isset($this->transportadoras[$apiTipo]), 404, 'Transportadora não cadastrada no catálogo.');

        $transportadora = Transportadora::where('api_tipo', $apiTipo)->first();

        return response()->json([
            'ok' => (bool) $transportadora,
            'api_tipo' => $apiTipo,
            'nome' => $this->transportadoras[$apiTipo],
            'message' => $transportadora
                ? 'Rota encontrada e configuração localizada.'
                : 'Rota encontrada, mas ainda sem configuração salva.',
        ]);
    }

    private function camposPadraoTransportadora(string $tipo): array
    {
        return match ($tipo) {
            'correios' => ['api_usuario', 'api_senha', 'api_token', 'api_contrato', 'cartao_postagem'],
            'jadlog' => ['api_token', 'api_contrato', 'cnpj'],
            'azul_cargo_express' => ['api_usuario', 'api_senha', 'api_token', 'api_contrato'],
            'loggi', 'uber_envios' => ['client_id', 'client_secret', 'api_token'],
            default => ['api_usuario', 'api_senha', 'api_token', 'api_contrato'],
        };
    }
}
