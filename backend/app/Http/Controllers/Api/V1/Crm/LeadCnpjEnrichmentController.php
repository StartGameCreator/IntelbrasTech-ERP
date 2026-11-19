<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\Cnpj\CnpjEnrichmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class LeadCnpjEnrichmentController extends Controller
{
    public function __invoke(
        Lead $lead,
        CnpjEnrichmentService $service
    ): JsonResponse {
        $user = request()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Sessão expirada ou usuário não autenticado. Entre novamente no sistema.',
            ], 401);
        }

        abort_unless(
            (int) $lead->empresa_id === (int) $user->empresa_id,
            403,
            'Você não possui permissão para enriquecer este Lead.'
        );

        if (!$lead->cnpj) {
            return response()->json([
                'message' => 'O Lead não possui CNPJ informado.',
            ], 422);
        }

        try {
            $dados = $service->consultar($lead->cnpj);

            DB::transaction(function () use ($lead, $dados): void {
                $endereco = collect([
                    $dados['logradouro'] ?? null,
                    $dados['numero'] ?? null,
                    $dados['complemento'] ?? null,
                    $dados['bairro'] ?? null,
                    $dados['municipio'] ?? null,
                    $dados['uf'] ?? null,
                    $dados['cep'] ?? null,
                ])->filter()->implode(', ');

                $lead->fill([
                    'empresa_nome' => $dados['nome_fantasia']
                        ?: $dados['razao_social']
                        ?: $lead->empresa_nome,
                    'razao_social' => $dados['razao_social'] ?? null,
                    'nome_fantasia' => $dados['nome_fantasia'] ?? null,
                    'situacao_cadastral' => $dados['situacao_cadastral'] ?? null,
                    'email' => $dados['email'] ?? $lead->email,
                    'telefone' => $dados['telefone'] ?? $lead->telefone,
                    'endereco_empresarial' => $endereco ?: null,
                    'logradouro' => $dados['logradouro'] ?? null,
                    'numero_endereco' => $dados['numero'] ?? null,
                    'complemento_endereco' => $dados['complemento'] ?? null,
                    'bairro' => $dados['bairro'] ?? null,
                    'cidade' => $dados['municipio'] ?? $lead->cidade,
                    'uf' => $dados['uf'] ?? $lead->uf,
                    'cep' => $dados['cep'] ?? null,
                    'cnae' => $dados['cnae_fiscal'] ?? null,
                    'atividade_principal' => $dados['cnae_fiscal_descricao'] ?? null,
                    'natureza_juridica' => $dados['natureza_juridica'] ?? null,
                    'porte_empresa' => $dados['porte'] ?? null,
                    'capital_social' => $dados['capital_social'] ?? null,
                    'socios' => json_encode(
                        $dados['socios'] ?? [],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'rfb_provider' => $dados['provider'] ?? null,
                    'rfb_consultado_em' => now(),
                    'rfb_dados' => json_encode(
                        $dados['raw'] ?? $dados,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ]);

                $lead->save();
            });

            return response()->json([
                'message' => 'Dados empresariais importados com sucesso.',
                'lead' => $lead->fresh(),
                'dados' => $dados,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
