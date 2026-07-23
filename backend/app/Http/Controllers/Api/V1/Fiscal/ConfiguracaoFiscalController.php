<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\CertificadoDigital;
use App\Models\ConfiguracaoFiscal;
use App\Models\NfceCsc;
use App\Services\Fiscal\SefazService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracaoFiscalController extends Controller
{
    public function show(): JsonResponse
    {
        $this->authorize('fiscal.view');
        $empresaId = auth()->user()->empresa_id;

        $config = ConfiguracaoFiscal::firstOrCreate(
            ['empresa_id' => $empresaId],
            ['versao_layout' => '4.00', 'ambiente_nfe' => 2, 'ambiente_nfce' => 2, 'serie_nfe' => '1', 'serie_nfce' => '1']
        );

        return response()->json([
            'configuracao' => $config,
            'cscs' => NfceCsc::where('empresa_id', $empresaId)->orderBy('ambiente')->orderBy('identificador')->get(),
            'certificado_ativo' => CertificadoDigital::where('empresa_id', $empresaId)->where('ativo', true)->exists(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('fiscal.create');
        $dados = $request->validate([
            'versao_layout' => 'required|in:4.00',
            'uf' => 'nullable|string|size:2',
            'ambiente_nfe' => 'required|integer|in:1,2',
            'ambiente_nfce' => 'required|integer|in:1,2',
            'serie_nfe' => 'required|string|max:5',
            'serie_nfce' => 'required|string|max:5',
            'ativo' => 'sometimes|boolean',
        ]);

        $dados['uf'] = $dados['uf'] ? strtoupper($dados['uf']) : null;
        $dados['empresa_id'] = auth()->user()->empresa_id;

        $config = ConfiguracaoFiscal::updateOrCreate(
            ['empresa_id' => $dados['empresa_id']],
            $dados
        );

        return response()->json(['message' => 'Configurações fiscais salvas.', 'configuracao' => $config]);
    }

    public function testar(SefazService $sefaz): JsonResponse
    {
        $this->authorize('fiscal.view');
        $config = ConfiguracaoFiscal::firstOrCreate(
            ['empresa_id' => auth()->user()->empresa_id],
            ['versao_layout' => '4.00', 'ambiente_nfe' => 2, 'ambiente_nfce' => 2]
        );

        $certificado = CertificadoDigital::where('empresa_id', auth()->user()->empresa_id)->where('ativo', true)->first();
        $problemas = [];
        if (!$config->uf) $problemas[] = 'Informe a UF do emitente.';
        if (!$certificado) $problemas[] = 'Nenhum certificado digital ativo foi encontrado.';
        if ($config->ambiente_nfce === 1 && !NfceCsc::where('empresa_id', auth()->user()->empresa_id)->where('ambiente', 1)->where('ativo', true)->exists()) {
            $problemas[] = 'Cadastre um CSC ativo de produção para NFC-e.';
        }
        try {
            $sefaz->assertDisponivel();
        } catch (\RuntimeException $e) {
            $problemas[] = $e->getMessage();
        }

        $statusSefaz = null;
        if (empty($problemas) && $certificado) {
            try {
                $statusSefaz = $sefaz->statusServico($certificado, (int) $config->ambiente_nfe);
                if ($sefaz->modo() === 'nfephp' && $statusSefaz['cStat'] !== '107') {
                    $problemas[] = "SEFAZ indisponível: [{$statusSefaz['cStat']}] {$statusSefaz['xMotivo']}";
                }
            } catch (\Throwable $e) {
                $problemas[] = 'Falha na conexão com a SEFAZ: '.$e->getMessage();
            }
        }

        return response()->json([
            'ok' => empty($problemas),
            'message' => empty($problemas)
                ? 'Configuração fiscal de testes validada.'
                : 'A configuração ainda possui pendências.',
            'problemas' => $problemas,
            'ambiente_nfe' => $config->ambiente_nfe,
            'ambiente_nfce' => $config->ambiente_nfce,
            'driver' => $sefaz->modo(),
            'status_sefaz' => $statusSefaz,
        ]);
    }

    public function storeCsc(Request $request): JsonResponse
    {
        $this->authorize('fiscal.create');
        $dados = $request->validate([
            'ambiente' => 'required|integer|in:1,2',
            'identificador' => 'required|string|max:10',
            'codigo' => 'required|string|min:8|max:128',
            'ativo' => 'sometimes|boolean',
        ]);

        $csc = NfceCsc::firstOrNew([
            'empresa_id' => auth()->user()->empresa_id,
            'ambiente' => $dados['ambiente'],
            'identificador' => trim($dados['identificador']),
        ]);
        $csc->ativo = $dados['ativo'] ?? true;
        $csc->codigo = trim($dados['codigo']);
        $csc->save();

        return response()->json(['message' => 'CSC salvo com segurança.', 'csc' => $csc], $csc->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyCsc(int $id): JsonResponse
    {
        $this->authorize('fiscal.create');
        NfceCsc::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id)->delete();
        return response()->json(['message' => 'CSC excluído.']);
    }
}
