<?php

namespace App\Http\Controllers\Api\V1\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\Empresa\EmpresaDataService;
use Illuminate\Http\{Request, JsonResponse};

class ClienteController extends Controller
{
    // ── GET /clientes ─────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('clientes.view');

        $q = Cliente::with(['vendedor:id,nome', 'tabelaPreco:id,nome'])
            ->when($request->busca, fn($q, $v)  => $q->busca($v))
            ->when($request->tipo,  fn($q, $v)  => $q->where('tipo', $v))
            ->when($request->ativo !== null, fn($q) => $q->where('ativo', $request->boolean('ativo')))
            ->when($request->estado, fn($q, $v) => $q->where('estado', $v))
            ->when($request->cidade, fn($q, $v) => $q->whereLike('cidade', "%{$v}%"))
            ->when($request->vendedor_id, fn($q, $v) => $q->where('vendedor_id', $v))
            ->when($request->origem, fn($q, $v) => $q->where('origem', $v))
            ->when($request->bloqueado !== null, fn($q) => $q->where('bloqueado', $request->boolean('bloqueado')))
            ->orderBy($request->get('order_by', 'razao_social'), $request->get('order', 'asc'));

        $perPage = min($request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /clientes ────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('clientes.create');

        // Máscaras pertencem somente à apresentação. No banco, documentos e
        // CEP são persistidos apenas com dígitos para evitar divergências de
        // tamanho, pesquisas duplicadas e falhas na emissão fiscal.
        $request->merge([
            'cpf_cnpj' => filled($request->input('cpf_cnpj'))
                ? preg_replace('/\D/', '', (string) $request->input('cpf_cnpj'))
                : null,
            'cep' => filled($request->input('cep'))
                ? preg_replace('/\D/', '', (string) $request->input('cep'))
                : null,
            'ibge' => filled($request->input('ibge'))
                ? preg_replace('/\D/', '', (string) $request->input('ibge'))
                : null,
        ]);

        $dados = $request->validate([
            'tipo'                => 'required|in:F,J',
            'razao_social'        => 'required|string|max:200',
            'nome_fantasia'       => 'nullable|string|max:200',
            'cpf_cnpj'            => ['nullable', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            'email'               => 'nullable|email|max:150',
            'telefone'            => 'nullable|string|max:20',
            'celular'             => 'nullable|string|max:20',
            'whatsapp'            => 'nullable|string|max:20',
            'rg_ie'               => 'nullable|string|max:30',
            'site'                => 'nullable|url|max:255',
            'inscricao_estadual'  => 'nullable|string|max:30',
            'inscricao_municipal' => 'nullable|string|max:30',
            'contribuinte_icms'   => 'nullable|boolean',
            'data_nascimento'     => 'nullable|date',
            'sexo'                => 'nullable|string|max:20',
            'profissao'           => 'nullable|string|max:120',
            'estado_civil'        => 'nullable|string|max:30',
            'foto'                => 'nullable|string|max:500',
            'cep'                 => ['nullable', 'string', 'size:8'],
            'logradouro'          => 'nullable|string|max:200',
            'numero'              => 'nullable|string|max:10',
            'complemento'         => 'nullable|string|max:100',
            'bairro'              => 'nullable|string|max:100',
            'cidade'              => 'nullable|string|max:100',
            'estado'              => 'nullable|string|size:2',
            'ibge'                => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'limite_credito'      => 'nullable|numeric|min:0',
            'desconto_padrao'     => 'nullable|numeric|min:0|max:100',
            'tabela_preco_id'     => 'nullable|exists:tabelas_preco,id',
            'vendedor_id'         => 'nullable|exists:usuarios,id',
            'observacoes'         => 'nullable|string',
            'tags'                => 'nullable|array',
            'origem'              => 'nullable|string|max:50',
        ], [
            'cpf_cnpj.regex' => 'Informe um CPF com 11 dígitos ou CNPJ com 14 dígitos.',
            'cep.size' => 'Informe um CEP com 8 dígitos.',
        ]);

        // O escopo de empresa protege as consultas, mas não preenche a chave
        // estrangeira durante a criação. Todo cliente deve pertencer à empresa
        // do usuário autenticado, inclusive no cadastro rápido do PDV.
        $dados['empresa_id'] = auth()->user()->empresa_id;

        // Geolocalizar endereço automaticamente
        $dados = $this->completarEnderecoFiscal($dados);

        $cliente = Cliente::create($dados);

        return response()->json([
            'message' => 'Cliente cadastrado com sucesso.',
            'cliente' => $cliente->fresh(['vendedor', 'tabelaPreco']),
        ], 201);
    }

    // ── GET /clientes/{id} ────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('clientes.view');

        // A base atual não possui os models ClienteEndereco e ClienteContato.
        // Carregar essas relações fazia qualquer acesso a /clientes/{id}
        // terminar em HTTP 500 antes mesmo de localizar o cliente.
        $cliente = Cliente::with([
            'vendedor:id,nome',
            'tabelaPreco:id,nome',
        ])->findOrFail($id);

        $cliente->append(['nome', 'documento_formatado']);

        // Resumo financeiro
        $resumo = [
            'total_pedidos'   => $cliente->pedidos()->count(),
            'valor_total'     => $cliente->pedidos()->whereIn('status', ['confirmado','faturado','entregue'])->sum('total'),
            'em_aberto'       => $cliente->contasReceber()->where('status', 'aberto')->sum('valor'),
            'em_atraso'       => $cliente->contasReceber()->where('status', 'aberto')
                                    ->where('data_vencimento', '<', today())->sum('valor'),
            'ultima_compra'   => $cliente->pedidos()->max('data_pedido'),
        ];

        return response()->json(compact('cliente', 'resumo'));
    }

    // ── PUT /clientes/{id} ────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('clientes.edit');

        $cliente = Cliente::findOrFail($id);

        $request->merge([
            'cpf_cnpj' => filled($request->input('cpf_cnpj'))
                ? preg_replace('/\D/', '', (string) $request->input('cpf_cnpj'))
                : null,
            'cep' => filled($request->input('cep'))
                ? preg_replace('/\D/', '', (string) $request->input('cep'))
                : null,
            'ibge' => filled($request->input('ibge'))
                ? preg_replace('/\D/', '', (string) $request->input('ibge'))
                : null,
        ]);

        $dados = $request->validate([
            'tipo'            => 'sometimes|in:F,J',
            'razao_social'    => 'sometimes|string|max:200',
            'nome_fantasia'   => 'nullable|string|max:200',
            'cpf_cnpj'        => ['nullable', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            'email'           => 'nullable|email|max:150',
            'telefone'        => 'nullable|string|max:20',
            'celular'         => 'nullable|string|max:20',
            'whatsapp'        => 'nullable|string|max:20',
            'cep'             => ['nullable', 'string', 'size:8'],
            'logradouro'      => 'nullable|string|max:200',
            'numero'          => 'nullable|string|max:10',
            'complemento'     => 'nullable|string|max:100',
            'bairro'          => 'nullable|string|max:100',
            'cidade'          => 'nullable|string|max:100',
            'estado'          => 'nullable|string|size:2',
            'ibge'            => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'limite_credito'  => 'nullable|numeric|min:0',
            'desconto_padrao' => 'nullable|numeric|min:0|max:100',
            'tabela_preco_id' => 'nullable|exists:tabelas_preco,id',
            'vendedor_id'     => 'nullable|exists:usuarios,id',
            'rg_ie'           => 'nullable|string|max:30',
            'site'            => 'nullable|url|max:255',
            'inscricao_estadual' => 'nullable|string|max:30',
            'inscricao_municipal' => 'nullable|string|max:30',
            'contribuinte_icms' => 'nullable|boolean',
            'data_nascimento' => 'nullable|date',
            'sexo'            => 'nullable|string|max:20',
            'profissao'       => 'nullable|string|max:120',
            'estado_civil'    => 'nullable|string|max:30',
            'foto'            => 'nullable|string|max:500',
            'observacoes'     => 'nullable|string',
            'ativo'           => 'sometimes|boolean',
            'tags'            => 'nullable|array',
        ]);

        // Re-geocodificar se CEP mudou
        $dados = $this->completarEnderecoFiscal(array_merge($cliente->toArray(), $dados), $dados);

        $cliente->update($dados);

        return response()->json([
            'message' => 'Cliente atualizado com sucesso.',
            'cliente' => $cliente->fresh(['vendedor', 'tabelaPreco']),
        ]);
    }

    // ── DELETE /clientes/{id} ─────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('clientes.delete');

        $cliente = Cliente::findOrFail($id);

        if ($cliente->pedidos()->whereNotIn('status', ['cancelado'])->exists()) {
            return response()->json([
                'message' => 'Cliente possui pedidos ativos e não pode ser excluído.',
            ], 422);
        }

        $cliente->delete();

        return response()->json(['message' => 'Cliente excluído com sucesso.']);
    }

    // ── GET /clientes/mapa ────────────────────────────────────

    public function mapa(Request $request): JsonResponse
    {
        $this->authorize('clientes.view');

        $clientes = Cliente::comGeo()
            ->when($request->estado, fn($q, $v) => $q->where('estado', $v))
            ->when($request->cidade, fn($q, $v) => $q->whereLike('cidade', "%{$v}%"))
            ->select('id', 'razao_social', 'nome_fantasia', 'cpf_cnpj', 'cidade', 'estado',
                     'latitude', 'longitude', 'tipo', 'ativo', 'bloqueado')
            ->limit(5000)
            ->get();

        return response()->json($clientes);
    }

    // ── GET /clientes/aniversariantes ─────────────────────────

    public function aniversariantes(Request $request): JsonResponse
    {
        $this->authorize('clientes.view');

        $mes = $request->get('mes', now()->month);

        $clientes = Cliente::aniversariantes($mes)
            ->ativo()
            ->select('id', 'razao_social', 'nome_fantasia', 'data_nascimento', 'celular', 'email')
            ->get()
            ->sortBy(fn ($c) => \Carbon\Carbon::parse($c->data_nascimento)->day)
            ->values();

        return response()->json($clientes);
    }

    // ── POST /clientes/{id}/bloquear ──────────────────────────

    public function bloquear(Request $request, int $id): JsonResponse
    {
        $this->authorize('clientes.edit');

        $request->validate(['motivo' => 'required|string|min:10']);

        Cliente::findOrFail($id)->update([
            'bloqueado'        => true,
            'motivo_bloqueio'  => $request->motivo,
        ]);

        return response()->json(['message' => 'Cliente bloqueado.']);
    }

    public function desbloquear(int $id): JsonResponse
    {
        $this->authorize('clientes.edit');

        Cliente::findOrFail($id)->update([
            'bloqueado'       => false,
            'motivo_bloqueio' => null,
        ]);

        return response()->json(['message' => 'Cliente desbloqueado.']);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function completarEnderecoFiscal(array $endereco, ?array $alteracoes = null): array
    {
        $resultado = $alteracoes ?? $endereco;
        $cep = preg_replace('/\D/', '', (string) ($endereco['cep'] ?? ''));
        $ibge = preg_replace('/\D/', '', (string) ($endereco['ibge'] ?? ''));

        if (strlen($cep) !== 8 || strlen($ibge) === 7) {
            return $resultado;
        }

        try {
            $consulta = app(EmpresaDataService::class)->consultarCep($cep);

            foreach (['cep', 'logradouro', 'complemento', 'bairro', 'cidade', 'estado', 'ibge'] as $campo) {
                if (blank($endereco[$campo] ?? null) && filled($consulta[$campo] ?? null)) {
                    $resultado[$campo] = $consulta[$campo];
                }
            }
        } catch (\Throwable) {
            // A indisponibilidade do provedor nao impede o cadastro. A validacao
            // fiscal continuara bloqueando emissoes sem codigo IBGE valido.
        }

        return $resultado;
    }
}
