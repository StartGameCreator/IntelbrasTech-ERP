<?php

namespace App\Http\Controllers\Api\V1\Utilitarios;

use App\Http\Controllers\Controller;
use App\Models\{Lead, Pipeline, PipelineEtapa};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CnpjController extends Controller
{
    public function fontes()
    {
        return response()->json([
            'fontes' => [
                [
                    'id' => 'cnpjws',
                    'nome' => 'CNPJ.ws',
                    'limite' => '3 consultas por minuto',
                    'endpoint' => 'https://publica.cnpj.ws/cnpj/{cnpj}',
                    'autenticacao' => false,
                    'recomendado' => true,
                ],
                [
                    'id' => 'cnpja',
                    'nome' => 'CNPJá Open',
                    'limite' => '5 consultas por minuto por IP',
                    'endpoint' => 'https://open.cnpja.com/office/{cnpj}',
                    'autenticacao' => false,
                    'recomendado' => false,
                ],
                [
                    'id' => 'opencnpj',
                    'nome' => 'OpenCNPJ',
                    'limite' => 'consulta pública gratuita',
                    'endpoint' => 'OpenCNPJ público',
                    'autenticacao' => false,
                    'recomendado' => false,
                ],
            ],
        ]);
    }

    public function consultar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cnpj' => ['required', 'string'],
            'fonte' => ['nullable', 'in:auto,cnpjws,cnpja,opencnpj'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Informe um CNPJ válido.', 'errors' => $validator->errors()], 422);
        }

        $cnpj = $this->somenteNumeros($request->input('cnpj'));
        if (strlen($cnpj) !== 14) {
            return response()->json(['message' => 'CNPJ deve conter 14 dígitos.'], 422);
        }

        $fonte = $request->input('fonte', 'auto');
        $fontes = $fonte === 'auto' ? ['cnpjws', 'cnpja', 'opencnpj'] : [$fonte];
        $tentativas = [];

        foreach ($fontes as $fonteAtual) {
            $resultado = $this->consultarFonte($fonteAtual, $cnpj);
            $tentativas[] = [
                'fonte' => $fonteAtual,
                'ok' => $resultado['ok'],
                'status' => $resultado['status'] ?? null,
                'erro' => $resultado['erro'] ?? null,
            ];

            if ($resultado['ok']) {
                return response()->json([
                    'message' => 'CNPJ consultado com sucesso.',
                    'fonte' => $fonteAtual,
                    'cnpj' => $cnpj,
                    'empresa' => $this->normalizar($resultado['dados'], $fonteAtual),
                    'raw' => $resultado['dados'],
                    'tentativas' => $tentativas,
                ]);
            }
        }

        return response()->json([
            'message' => 'Não foi possível consultar este CNPJ nas fontes públicas configuradas.',
            'cnpj' => $cnpj,
            'tentativas' => $tentativas,
        ], 502);
    }

    public function lote(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cnpjs' => ['required', 'array', 'min:1', 'max:20'],
            'cnpjs.*' => ['required', 'string'],
            'fonte' => ['nullable', 'in:auto,cnpjws,cnpja,opencnpj'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Envie uma lista de CNPJs válida.', 'errors' => $validator->errors()], 422);
        }

        $resultados = [];
        foreach ($request->input('cnpjs') as $item) {
            $cnpj = $this->somenteNumeros($item);
            if (strlen($cnpj) !== 14) {
                $resultados[] = ['cnpj' => $cnpj, 'ok' => false, 'message' => 'CNPJ inválido.'];
                continue;
            }

            $fonte = $request->input('fonte', 'auto');
            $fontes = $fonte === 'auto' ? ['cnpjws', 'cnpja', 'opencnpj'] : [$fonte];
            $encontrado = null;
            foreach ($fontes as $fonteAtual) {
                $resultado = $this->consultarFonte($fonteAtual, $cnpj);
                if ($resultado['ok']) {
                    $encontrado = [
                        'cnpj' => $cnpj,
                        'ok' => true,
                        'fonte' => $fonteAtual,
                        'empresa' => $this->normalizar($resultado['dados'], $fonteAtual),
                    ];
                    break;
                }
            }
            $resultados[] = $encontrado ?: ['cnpj' => $cnpj, 'ok' => false, 'message' => 'Não encontrado ou limite da API atingido.'];
        }

        return response()->json([
            'message' => 'Consulta em lote concluída.',
            'total' => count($resultados),
            'resultados' => $resultados,
        ]);
    }


    public function importarLead(Request $request)
    {
        $this->authorize('crm.create');

        $dados = $request->validate([
            'cnpj' => ['required', 'string', 'max:20'],
            'razao_social' => ['nullable', 'string', 'max:200'],
            'nome_fantasia' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'atividade_principal' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:2'],
            'fonte' => ['nullable', 'string', 'max:40'],
        ]);

        $empresaId = auth()->user()->empresa_id;
        $cnpjNumeros = $this->somenteNumeros($dados['cnpj']);
        $tagCnpj = 'cnpj:' . $cnpjNumeros;

        $existente = Lead::where('empresa_id', $empresaId)
            ->where('tags', 'like', '%' . $tagCnpj . '%')
            ->first();

        if ($existente) {
            return response()->json([
                'message' => 'Esta empresa já está salva no CRM.',
                'lead' => $existente,
            ], 409);
        }

        $pipeline = Pipeline::where('empresa_id', $empresaId)->where('ativo', true)->first();
        $etapaId = $pipeline
            ? PipelineEtapa::where('pipeline_id', $pipeline->id)->orderBy('ordem')->value('id')
            : null;

        $nome = $dados['nome_fantasia'] ?: ($dados['razao_social'] ?: $dados['cnpj']);
        $tags = array_filter([
            'busca-cnpj',
            $tagCnpj,
            !empty($dados['fonte']) ? 'fonte:' . $dados['fonte'] : null,
            !empty($dados['uf']) ? 'uf:' . strtoupper($dados['uf']) : null,
        ]);

        $lead = Lead::create([
            'empresa_id' => $empresaId,
            'pipeline_id' => $pipeline?->id,
            'etapa_id' => $etapaId,
            'responsavel_id' => auth()->id(),
            'nome' => $nome,
            'empresa_nome' => $dados['razao_social'] ?: $nome,
            'email' => $dados['email'] ?? null,
            'telefone' => $dados['telefone'] ?? null,
            'origem' => 'busca_cnpj',
            'titulo' => 'Prospecção CNPJ: ' . $nome,
            'status' => Lead::STATUS_ABERTO,
            'probabilidade' => 10,
            'tags' => implode(',', $tags),
        ]);

        return response()->json([
            'message' => 'Empresa salva como lead no CRM.',
            'lead' => $lead->fresh(['etapa', 'responsavel']),
        ], 201);
    }

    private function consultarFonte(string $fonte, string $cnpj): array
    {
        try {
            $url = match ($fonte) {
                'cnpjws' => "https://publica.cnpj.ws/cnpj/{$cnpj}",
                'cnpja' => "https://open.cnpja.com/office/{$cnpj}",
                'opencnpj' => "https://api.opencnpj.org/{$cnpj}",
                default => null,
            };

            if (!$url) {
                return ['ok' => false, 'erro' => 'Fonte inválida.'];
            }

            $resposta = Http::acceptJson()
                ->timeout(20)
                ->retry(1, 800)
                ->get($url);

            if (!$resposta->successful()) {
                return ['ok' => false, 'status' => $resposta->status(), 'erro' => $resposta->body()];
            }

            $dados = $resposta->json();
            if (!$dados || !is_array($dados)) {
                return ['ok' => false, 'status' => $resposta->status(), 'erro' => 'Resposta vazia ou inválida.'];
            }

            return ['ok' => true, 'status' => $resposta->status(), 'dados' => $dados];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }

    private function normalizar(array $dados, string $fonte): array
    {
        if ($fonte === 'cnpjws') {
            $estab = $dados['estabelecimento'] ?? [];
            $cidade = $estab['cidade'] ?? [];
            $estado = $estab['estado'] ?? [];
            $atividade = $estab['atividade_principal'] ?? [];
            return [
                'cnpj' => $this->formatarCnpj(($estab['cnpj'] ?? '') ?: (($estab['cnpj_raiz'] ?? '') . ($estab['cnpj_ordem'] ?? '') . ($estab['cnpj_digito_verificador'] ?? ''))),
                'razao_social' => $dados['razao_social'] ?? null,
                'nome_fantasia' => $estab['nome_fantasia'] ?? null,
                'situacao' => $estab['situacao_cadastral'] ?? null,
                'porte' => $dados['porte']['descricao'] ?? null,
                'natureza_juridica' => $dados['natureza_juridica']['descricao'] ?? null,
                'cnae_principal' => $atividade['id'] ?? null,
                'atividade_principal' => $atividade['descricao'] ?? null,
                'uf' => $estado['sigla'] ?? null,
                'cidade' => $cidade['nome'] ?? null,
                'bairro' => $estab['bairro'] ?? null,
                'logradouro' => trim(($estab['tipo_logradouro'] ?? '') . ' ' . ($estab['logradouro'] ?? '')),
                'numero' => $estab['numero'] ?? null,
                'cep' => $estab['cep'] ?? null,
                'email' => $estab['email'] ?? null,
                'telefone' => trim(($estab['ddd1'] ?? '') . ' ' . ($estab['telefone1'] ?? '')),
                'simples' => $dados['simples'] ?? null,
                'socios' => $dados['socios'] ?? [],
            ];
        }

        if ($fonte === 'cnpja') {
            $address = $dados['address'] ?? [];
            $main = $dados['mainActivity'] ?? [];
            $company = $dados['company'] ?? [];
            return [
                'cnpj' => $this->formatarCnpj($dados['taxId'] ?? ''),
                'razao_social' => $company['name'] ?? $dados['alias'] ?? null,
                'nome_fantasia' => $dados['alias'] ?? null,
                'situacao' => $dados['status']['text'] ?? null,
                'porte' => $company['size']['text'] ?? null,
                'natureza_juridica' => $company['nature']['text'] ?? null,
                'cnae_principal' => $main['id'] ?? null,
                'atividade_principal' => $main['text'] ?? null,
                'uf' => $address['state'] ?? null,
                'cidade' => $address['city'] ?? null,
                'bairro' => $address['district'] ?? null,
                'logradouro' => $address['street'] ?? null,
                'numero' => $address['number'] ?? null,
                'cep' => $address['zip'] ?? null,
                'email' => collect($dados['emails'] ?? [])->first()['address'] ?? null,
                'telefone' => collect($dados['phones'] ?? [])->map(fn($p) => trim(($p['area'] ?? '') . ' ' . ($p['number'] ?? '')))->first(),
                'simples' => $company['simples'] ?? null,
                'socios' => $company['members'] ?? [],
            ];
        }

        return [
            'cnpj' => $this->formatarCnpj($dados['cnpj'] ?? $dados['taxId'] ?? ''),
            'razao_social' => $dados['razao_social'] ?? $dados['nome'] ?? $dados['name'] ?? null,
            'nome_fantasia' => $dados['nome_fantasia'] ?? $dados['fantasia'] ?? $dados['alias'] ?? null,
            'situacao' => $dados['situacao'] ?? $dados['status'] ?? null,
            'porte' => $dados['porte'] ?? null,
            'natureza_juridica' => $dados['natureza_juridica'] ?? null,
            'cnae_principal' => $dados['cnae_fiscal'] ?? $dados['cnae_principal'] ?? null,
            'atividade_principal' => $dados['atividade_principal'] ?? null,
            'uf' => $dados['uf'] ?? null,
            'cidade' => $dados['municipio'] ?? $dados['cidade'] ?? null,
            'bairro' => $dados['bairro'] ?? null,
            'logradouro' => $dados['logradouro'] ?? null,
            'numero' => $dados['numero'] ?? null,
            'cep' => $dados['cep'] ?? null,
            'email' => $dados['email'] ?? null,
            'telefone' => $dados['telefone'] ?? null,
            'simples' => $dados['simples'] ?? null,
            'socios' => $dados['socios'] ?? [],
        ];
    }

    private function somenteNumeros(?string $valor): string
    {
        return preg_replace('/\D+/', '', $valor ?? '');
    }

    private function formatarCnpj(?string $cnpj): ?string
    {
        $cnpj = $this->somenteNumeros($cnpj);
        if (strlen($cnpj) !== 14) return $cnpj ?: null;
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
}
