<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

class NfseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fiscal.view');

        return response()->json(NotaFiscal::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'NFSe')
            ->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', 20), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fiscal.create');

        $data = $request->validate([
            'destinatario_nome' => ['required', 'string', 'max:200'],
            'destinatario_cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'destinatario_email' => ['nullable', 'email'],
            'descricao_servico' => ['required', 'string', 'max:300'],
            'codigo_servico' => ['nullable', 'string', 'max:50'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $nota = DB::transaction(function () use ($data) {
            $valor = round((float) $data['valor'], 2);
            $nota = NotaFiscal::create([
                'empresa_id' => auth()->user()->empresa_id,
                'tipo' => 'NFSe',
                'modelo' => 'NFS-e',
                'natureza_operacao' => 'Prestação de serviços',
                'ambiente' => 2,
                'finalidade' => 'normal',
                'destinatario_nome' => $data['destinatario_nome'],
                'destinatario_cpf_cnpj' => $data['destinatario_cpf_cnpj'] ?? null,
                'destinatario_email' => $data['destinatario_email'] ?? null,
                'valor_produtos' => $valor,
                'valor_total' => $valor,
                'status' => NotaFiscal::STATUS_RASCUNHO,
                'data_emissao' => now(),
                'informacoes_adicionais' => $data['observacoes'] ?? null,
            ]);

            NotaFiscalItem::create([
                'nota_fiscal_id' => $nota->id,
                'numero_item' => 1,
                'codigo' => $data['codigo_servico'] ?? null,
                'descricao' => $data['descricao_servico'],
                'unidade' => 'UN',
                'quantidade' => 1,
                'valor_unitario' => $valor,
                'valor_total' => $valor,
            ]);

            return $nota->load('itens');
        });

        return response()->json([
            'message' => 'NFS-e de serviço criada como rascunho.',
            'data' => $nota,
        ], 201);
    }
}
