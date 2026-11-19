<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\FiscalEvento;
use App\Models\NotaFiscal;
use App\Services\Fiscal\NFeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CentralFiscalController extends Controller
{
    public function __construct(private readonly NFeService $nfe) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('fiscal.view');
        $base = NotaFiscal::query();
        $hoje = now()->toDateString();
        return response()->json([
            'total' => (clone $base)->count(),
            'autorizadas' => (clone $base)->where('status', NotaFiscal::STATUS_AUTORIZADA)->count(),
            'canceladas' => (clone $base)->where('status', NotaFiscal::STATUS_CANCELADA)->count(),
            'pendentes' => (clone $base)->whereIn('status', ['rascunho','processando','rejeitada'])->count(),
            'emitidas_hoje' => (clone $base)->whereDate('data_emissao', $hoje)->count(),
            'valor_autorizado' => (float) (clone $base)->where('status', NotaFiscal::STATUS_AUTORIZADA)->sum('valor_total'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('fiscal.view');
        $q = NotaFiscal::with(['pedido.cliente:id,razao_social,nome_fantasia'])
            ->when($request->filled('tipo'), fn($q) => $q->where('tipo', $request->string('tipo')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('numero'), fn($q) => $q->where('numero', 'like', '%'.$request->input('numero').'%'))
            ->when($request->filled('chave'), fn($q) => $q->where('chave_acesso', 'like', '%'.$request->input('chave').'%'))
            ->when($request->filled('documento'), fn($q) => $q->where('destinatario_cpf_cnpj', 'like', '%'.$request->input('documento').'%'))
            ->when($request->filled('inicio'), fn($q) => $q->whereDate('data_emissao', '>=', $request->input('inicio')))
            ->when($request->filled('fim'), fn($q) => $q->whereDate('data_emissao', '<=', $request->input('fim')))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $v = $request->input('busca');
                $q->where(fn($qq) => $qq->where('destinatario_nome', 'like', "%{$v}%")
                    ->orWhere('destinatario_cpf_cnpj', 'like', "%{$v}%")
                    ->orWhere('chave_acesso', 'like', "%{$v}%")
                    ->orWhere('numero', 'like', "%{$v}%"));
            })
            ->latest('data_emissao');
        return response()->json($q->paginate(min(max((int)$request->input('per_page', 20), 1), 100)));
    }

    public function segundaVia(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $this->authorize('fiscal.view');
        $nota = NotaFiscal::findOrFail($id);
        if (!in_array($nota->status, [NotaFiscal::STATUS_AUTORIZADA, NotaFiscal::STATUS_CANCELADA], true)) {
            return response()->json(['message' => 'A segunda via só está disponível para notas autorizadas ou canceladas.'], 422);
        }
        if (!$nota->chave_acesso) return response()->json(['message' => 'Nota sem chave de acesso.'], 422);
        $disk = (string) config('services.sefaz.storage_disk', 'local');
        $caminho = "danfe/{$nota->empresa_id}/{$nota->chave_acesso}.pdf";
        if (!Storage::disk($disk)->exists($caminho)) {
            return response()->json(['message' => 'O PDF original não foi encontrado. Gere o DANFE pela tela da nota antes de solicitar a segunda via.'], 404);
        }
        $this->registrar($request, $nota, 'segunda_via', 'Segunda via do DANFE gerada');
        return Storage::disk($disk)->response($caminho, "SEGUNDA-VIA-{$nota->tipo}-{$nota->numero}.pdf", ['Content-Type'=>'application/pdf']);
    }

    public function xml(Request $request, string $id)
    {
        $this->authorize('fiscal.view');
        $nota = NotaFiscal::findOrFail($id);
        if (!$nota->xml) return response()->json(['message' => 'XML não encontrado.'], 404);
        $this->registrar($request, $nota, 'download_xml', 'XML fiscal baixado');
        return response($nota->xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nota->tipo.'-'.$nota->chave_acesso.'.xml"',
        ]);
    }

    public function consultar(Request $request, string $id): JsonResponse
    {
        $this->authorize('fiscal.view');
        $nota = NotaFiscal::findOrFail($id);
        if (!$nota->chave_acesso) return response()->json(['message' => 'Nota sem chave de acesso.'], 422);
        try {
            $resultado = $this->nfe->consultar($nota->chave_acesso, auth()->user()->empresa);
            $this->registrar($request, $nota, 'consulta_sefaz', 'Situação consultada na SEFAZ', ['resultado'=>$resultado]);
            return response()->json($resultado);
        } catch (Throwable $e) {
            return response()->json(['message'=>$e->getMessage()], 422);
        }
    }

    public function historico(string $id): JsonResponse
    {
        $this->authorize('fiscal.view');
        NotaFiscal::findOrFail($id);
        return response()->json(FiscalEvento::where('nota_fiscal_id', $id)->latest()->limit(100)->get());
    }

    private function registrar(Request $request, NotaFiscal $nota, string $tipo, string $descricao, array $dados = []): void
    {
        FiscalEvento::create([
            'empresa_id'=>$nota->empresa_id, 'nota_fiscal_id'=>$nota->id,
            'usuario_id'=>auth()->id(), 'tipo'=>$tipo, 'descricao'=>$descricao,
            'ip'=>$request->ip(), 'user_agent'=>substr((string)$request->userAgent(), 0, 2000), 'dados'=>$dados ?: null,
        ]);
    }
}
