<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\CertificadoDigital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificadoDigitalController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('fiscal.view');

        return response()->json([
            'data' => CertificadoDigital::query()
                ->orderByDesc('ativo')
                ->orderByDesc('validade')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (CertificadoDigital $cert) => $this->present($cert)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fiscal.emitir');

        $data = $this->validateData($request, true);
        // O UploadedFile não pode sobrescrever o caminho definitivo retornado pelo Storage.
        unset($data['arquivo']);
        $metadata = [];
        $arquivo = null;

        if ($request->hasFile('arquivo')) {
            $metadata = $this->extractMetadata($request->file('arquivo')->getRealPath(), $data['senha'] ?? null);
            $arquivo = $request->file('arquivo')->store('certificados-digitais/'.auth()->user()->empresa_id, 'local');
        }

        if (($data['ativo'] ?? true) === true) {
            CertificadoDigital::where('empresa_id', auth()->user()->empresa_id)->update(['ativo' => false]);
        }

        $certificado = CertificadoDigital::create(array_merge([
            'empresa_id' => auth()->user()->empresa_id,
            'arquivo' => $arquivo,
            'ativo' => $data['ativo'] ?? true,
            'armazenamento' => $data['armazenamento'] ?? 'servidor',
        ], $data, $metadata));

        return response()->json([
            'message' => 'Certificado digital cadastrado com sucesso.',
            'data' => $this->present($certificado->fresh()),
        ], 201);
    }

    public function inspect(Request $request): JsonResponse
    {
        $this->authorize('fiscal.emitir');

        $validated = $request->validate([
            'arquivo' => ['required', 'file', 'max:10240', 'extensions:pfx,p12'],
            'senha' => ['required', 'string', 'max:255'],
        ], [
            'arquivo.extensions' => 'Selecione um certificado com extensão .pfx ou .p12.',
            'arquivo.max' => 'O certificado não pode ultrapassar 10 MB.',
        ]);

        $metadata = $this->extractMetadata(
            $request->file('arquivo')->getRealPath(),
            $validated['senha']
        );

        return response()->json([
            'message' => 'Certificado identificado com sucesso.',
            'data' => $metadata,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');
        $certificado = CertificadoDigital::findOrFail($id);
        $data = $this->validateData($request, false);
        // Mantém no banco somente o caminho definitivo, nunca o arquivo temporário do PHP.
        unset($data['arquivo']);
        $metadata = [];

        if ($request->hasFile('arquivo')) {
            $metadata = $this->extractMetadata($request->file('arquivo')->getRealPath(), $data['senha'] ?? null);
            if ($certificado->arquivo) Storage::disk('local')->delete($certificado->arquivo);
            $data['arquivo'] = $request->file('arquivo')->store('certificados-digitais/'.auth()->user()->empresa_id, 'local');
        }

        if (($data['ativo'] ?? false) === true) {
            CertificadoDigital::where('empresa_id', auth()->user()->empresa_id)
                ->whereKeyNot($certificado->id)
                ->update(['ativo' => false]);
        }

        $certificado->update(array_merge($data, $metadata));

        return response()->json([
            'message' => 'Certificado digital atualizado com sucesso.',
            'data' => $this->present($certificado->fresh()),
        ]);
    }

    public function activate(int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');
        $certificado = CertificadoDigital::findOrFail($id);
        CertificadoDigital::where('empresa_id', auth()->user()->empresa_id)->update(['ativo' => false]);
        $certificado->update(['ativo' => true]);

        return response()->json(['message' => 'Certificado definido como ativo.', 'data' => $this->present($certificado->fresh())]);
    }

    public function download(int $id): StreamedResponse
    {
        $this->authorize('fiscal.emitir');
        $certificado = CertificadoDigital::findOrFail($id);
        abort_unless($certificado->arquivo && Storage::disk('local')->exists($certificado->arquivo), 404, 'Arquivo do certificado não encontrado.');

        $extension = pathinfo($certificado->arquivo, PATHINFO_EXTENSION) ?: 'pfx';
        $filename = 'certificado-'.($certificado->documento ?: $certificado->id).'.'.$extension;
        return Storage::disk('local')->download($certificado->arquivo, $filename);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');
        $certificado = CertificadoDigital::findOrFail($id);
        if ($certificado->arquivo) Storage::disk('local')->delete($certificado->arquivo);
        $certificado->delete();

        return response()->json(['message' => 'Certificado digital removido com sucesso.']);
    }

    private function validateData(Request $request, bool $creating): array
    {
        return $request->validate([
            'tipo' => [$creating ? 'required' : 'sometimes', Rule::in(['A1', 'A3'])],
            'arquivo' => [$creating ? 'required' : 'sometimes', 'file', 'max:10240', 'extensions:pfx,p12'],
            'senha' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'armazenamento' => ['nullable', Rule::in(['servidor', 'cliente', 'windows'])],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['nullable', 'boolean'],
        ], [
            'arquivo.extensions' => 'Selecione um certificado com extensão .pfx ou .p12.',
            'arquivo.max' => 'O certificado não pode ultrapassar 10 MB.',
        ]);
    }

    private function extractMetadata(string $path, ?string $password): array
    {
        abort_unless(
            function_exists('openssl_pkcs12_read'),
            422,
            'O OpenSSL do servidor não está habilitado para ler certificados PFX/P12.'
        );
        $content = file_get_contents($path);
        $certs = [];
        if (! @openssl_pkcs12_read($content, $certs, $password ?? '')) {
            abort(422, 'Não foi possível abrir o certificado. Verifique a senha e o arquivo PFX/P12.');
        }
        $parsed = openssl_x509_parse($certs['cert'] ?? '');
        abort_unless(is_array($parsed), 422, 'O certificado não possui metadados X.509 válidos.');

        $subject = $parsed['subject'] ?? [];
        $issuer = $parsed['issuer'] ?? [];
        $serial = $parsed['serialNumberHex'] ?? ($parsed['serialNumber'] ?? null);
        $subjectText = implode(' ', array_filter(array_map(
            static fn ($value) => is_scalar($value) ? (string) $value : null,
            array_values($subject)
        )));
        preg_match('/(?<!\d)(\d{11}|\d{14})(?!\d)/', preg_replace('/\D+/', ' ', $subjectText), $document);

        $titular = trim((string) ($subject['CN'] ?? ''));
        $validade = isset($parsed['validTo_time_t']) ? date('Y-m-d', $parsed['validTo_time_t']) : null;
        abort_if(! $validade, 422, 'Não foi possível identificar a validade do certificado.');
        abort_if($parsed['validTo_time_t'] < time(), 422, 'Este certificado está vencido desde '.date('d/m/Y', $parsed['validTo_time_t']).'.');

        return array_filter([
            'nome' => $titular ?: null,
            'titular' => $titular ?: null,
            'documento' => $document[1] ?? null,
            'emissor' => $issuer['CN'] ?? ($issuer['O'] ?? null),
            'serie' => $serial ? (string) $serial : null,
            'validade_inicio' => isset($parsed['validFrom_time_t']) ? date('Y-m-d', $parsed['validFrom_time_t']) : null,
            'validade' => $validade,
            'thumbprint' => isset($certs['cert']) ? openssl_x509_fingerprint($certs['cert'], 'sha256') : null,
        ]);
    }

    private function present(CertificadoDigital $cert): array
    {
        return [
            'id' => $cert->id,
            'tipo' => $cert->tipo,
            'nome' => $cert->nome,
            'titular' => $cert->titular,
            'documento' => $cert->documento,
            'emissor' => $cert->emissor,
            'validade_inicio' => optional($cert->validade_inicio)->format('Y-m-d'),
            'validade' => optional($cert->validade)->format('Y-m-d'),
            'serie' => $cert->serie,
            'thumbprint' => $cert->thumbprint,
            'armazenamento' => $cert->armazenamento,
            'observacoes' => $cert->observacoes,
            'ativo' => (bool) $cert->ativo,
            'possui_arquivo' => (bool) $cert->arquivo,
            'dias_para_vencer' => $cert->validade ? now()->startOfDay()->diffInDays($cert->validade, false) : null,
            'created_at' => optional($cert->created_at)->toISOString(),
        ];
    }
}
