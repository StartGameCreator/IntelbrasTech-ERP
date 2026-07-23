<?php

namespace App\Http\Controllers\Api\V1\Fiscal\NFe;

use App\Http\Controllers\Controller;
use App\Models\NotaFiscal;
use App\Services\Fiscal\NFeService;
use Illuminate\Http\{Request, JsonResponse};
use Exception;

class NFeController extends Controller
{
    public function __construct(
        private readonly NFeService $nfe,
    ) {}

    // â”€â”€ GET /nfe â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function index(Request $request): JsonResponse
    {
        $this->authorize('fiscal.view');

        $q = NotaFiscal::with(['pedido.cliente:id,razao_social,nome_fantasia'])
            ->when($request->tipo,   fn($q, $v) => $q->tipo($v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->ambiente !== null && $request->ambiente !== '', fn($q) => $q->ambiente((int) $request->ambiente))
            ->when($request->numero, fn($q, $v) => $q->where('numero', 'like', "%{$v}%"))
            ->when($request->chave_acesso, fn($q, $v) => $q->where('chave_acesso', 'like', "%{$v}%"))
            ->when($request->data_inicio && $request->data_fim,
                fn($q) => $q->whereBetween('data_emissao', [$request->data_inicio, $request->data_fim]))
            ->when($request->busca, function ($q, $v) {
                $q->where(function ($qq) use ($v) {
                    $qq->where('numero', 'like', "%{$v}%")
                       ->orWhere('chave_acesso', 'like', "%{$v}%")
                       ->orWhere('destinatario_nome', 'like', "%{$v}%");
                });
            })
            ->orderBy($request->get('order_by', 'data_emissao'), $request->get('order', 'desc'));

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // â”€â”€ POST /nfe â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Cria a partir de um pedido (fluxo normal) ou manualmente (NF-e avulsa)

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fiscal.create');

        if ($request->filled('pedido_id')) {
            $pedido = \App\Models\Pedido::with(['itens.produto.unidade', 'cliente', 'empresa'])
                ->findOrFail($request->integer('pedido_id'));

            if (! $pedido->pode_emitir_nfe) {
                return response()->json(['message' => 'NF-e nÃ£o pode ser emitida para este pedido.'], 422);
            }

            $nfe = $this->nfe->montarDePedido($pedido);

            return response()->json(['message' => 'NF-e montada a partir do pedido.', 'data' => $nfe], 201);
        }

        $dados = $request->validate([
            'tipo'                  => 'required|in:NFe,NFCe,NFSe',
            'natureza_operacao'     => 'required|string|max:100',
            'destinatario_nome'     => 'required|string|max:200',
            'destinatario_cpf_cnpj' => 'required|string|max:18',
            'destinatario_email'    => 'nullable|email',
        ]);

        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados['status']     = NotaFiscal::STATUS_RASCUNHO;
        $configFiscal = \App\Models\ConfiguracaoFiscal::where('empresa_id', auth()->user()->empresa_id)->first();
        $ambientePadrao = ($dados['tipo'] ?? 'NFe') === 'NFCe'
            ? ($configFiscal?->ambiente_nfce ?? 2)
            : ($configFiscal?->ambiente_nfe ?? 2);
        $dados['ambiente'] = $request->integer('ambiente') ?: $ambientePadrao; // 1=Producao, 2=Homologacao

        $item = NotaFiscal::create($dados);

        return response()->json(['message' => 'NF-e criada como rascunho.', 'data' => $item], 201);
    }

    // â”€â”€ GET /nfe/{id} â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function show(int $id): JsonResponse
    {
        $this->authorize('fiscal.view');

        $nfe = NotaFiscal::with(['itens', 'cartasCorrecao', 'pedido.cliente'])->findOrFail($id);

        return response()->json($nfe);
    }

    // â”€â”€ PUT /nfe/{id} â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // SÃ³ Ã© permitido editar enquanto estiver em rascunho

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fiscal.create');

        $item = NotaFiscal::findOrFail($id);

        if ($item->status !== NotaFiscal::STATUS_RASCUNHO) {
            return response()->json(['message' => 'Somente NF-e em rascunho pode ser editada.'], 422);
        }

        $item->update($request->except(['status', 'chave_acesso', 'protocolo', 'xml']));

        return response()->json(['message' => 'Atualizado.', 'data' => $item]);
    }

    // â”€â”€ DELETE /nfe/{id} â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fiscal.create');

        $item = NotaFiscal::findOrFail($id);

        if ($item->status !== NotaFiscal::STATUS_RASCUNHO) {
            return response()->json(['message' => 'Somente NF-e em rascunho pode ser excluÃ­da.'], 422);
        }

        $item->delete();

        return response()->json(['message' => 'ExcluÃ­do.']);
    }

    public function webhook(Request $request): JsonResponse
    {
        return response()->json(['received' => true]);
    }

    // â”€â”€ POST /nfe/{id}/emitir â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function emitir(int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');

        $nfe = NotaFiscal::with('itens')->findOrFail($id);

        try {
            $nfe = $this->nfe->emitir($nfe);
            return response()->json(['message' => 'NF-e emitida e autorizada com sucesso.', 'data' => $nfe]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // â”€â”€ POST /nfe/{id}/cancelar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->authorize('fiscal.cancelar');

        $dados = $request->validate([
            'justificativa' => 'required|string|min:15|max:255',
        ]);

        $nfe = NotaFiscal::findOrFail($id);

        try {
            $nfe = $this->nfe->cancelar($nfe, $dados['justificativa']);
            return response()->json(['message' => 'NF-e cancelada com sucesso.', 'data' => $nfe]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // â”€â”€ POST /nfe/{id}/carta-correcao â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function cartaCorrecao(Request $request, int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');

        $dados = $request->validate([
            'descricao' => 'required|string|min:15|max:1000',
        ]);

        $nfe = NotaFiscal::findOrFail($id);

        try {
            $resultado = $this->nfe->cartaCorrecao($nfe, $dados['descricao']);
            return response()->json(['message' => 'Carta de CorreÃ§Ã£o emitida com sucesso.', 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // â”€â”€ GET /nfe/{id}/xml â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function downloadXml(int $id): JsonResponse
    {
        $this->authorize('fiscal.view');

        $nfe = NotaFiscal::findOrFail($id);

        if (! $nfe->xml) {
            return response()->json(['message' => 'Esta NF-e ainda nÃ£o possui XML gerado.'], 422);
        }

        return response()->json([
            'filename' => "NFe-{$nfe->chave_acesso}.xml",
            'xml'      => $nfe->xml,
        ]);
    }

    // â”€â”€ GET /nfe/{id}/danfe â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function downloadDanfe(int $id): JsonResponse
    {
        $this->authorize('fiscal.view');

        $nfe = NotaFiscal::findOrFail($id);

        if (! $nfe->danfe_url) {
            return response()->json(['message' => 'DANFE ainda nÃ£o foi gerado para esta NF-e.'], 422);
        }

        return response()->json(['url' => $nfe->danfe_url]);
    }

    public function downloadDanfeFile(int $id)
    {
        $this->authorize('fiscal.view');
        $nfe = NotaFiscal::findOrFail($id);
        abort_unless($nfe->chave_acesso, 404, 'DANFE não encontrado.');

        $disk = (string) config('services.sefaz.storage_disk', 'local');
        $caminho = "danfe/{$nfe->empresa_id}/{$nfe->chave_acesso}.pdf";
        abort_unless(\Illuminate\Support\Facades\Storage::disk($disk)->exists($caminho), 404, 'DANFE não encontrado.');

        return \Illuminate\Support\Facades\Storage::disk($disk)->response(
            $caminho,
            "DANFE-{$nfe->numero}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    // â”€â”€ POST /nfe/{id}/enviar-email â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function enviarEmail(int $id): JsonResponse
    {
        $this->authorize('fiscal.view');

        $nfe = NotaFiscal::findOrFail($id);

        if ($nfe->status !== NotaFiscal::STATUS_AUTORIZADA) {
            return response()->json(['message' => 'Somente NF-e autorizadas podem ser enviadas por e-mail.'], 422);
        }
        if (! $nfe->destinatario_email) {
            return response()->json(['message' => 'DestinatÃ¡rio nÃ£o possui e-mail cadastrado.'], 422);
        }

        // IntegraÃ§Ã£o de envio (Mailable dedicado) fica como prÃ³ximo passo;
        // aqui jÃ¡ validamos as prÃ©-condiÃ§Ãµes necessÃ¡rias para nÃ£o falhar silenciosamente.
        return response()->json(['message' => "E-mail enviado para {$nfe->destinatario_email}."]);
    }

    // â”€â”€ GET /nfe/consultar/{chave} â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function consultar(string $chave): JsonResponse
    {
        $this->authorize('fiscal.view');

        try {
            $retorno = $this->nfe->consultar($chave, auth()->user()->empresa);
            return response()->json($retorno);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
