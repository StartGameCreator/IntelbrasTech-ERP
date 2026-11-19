<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Gera o DANFE (Documento Auxiliar da NF-e) em PDF a partir de uma NF-e
 * autorizada e devolve a URL pública/assinada do arquivo salvo no storage
 * (S3/MinIO, conforme configurado em config/filesystems.php).
 *
 * Dependência necessária no backend (ainda não presente no composer.json):
 *   composer require barryvdh/laravel-dompdf
 *
 * A view usada (resources/views/fiscal/danfe.blade.php) deve ser criada
 * seguindo o layout retrato padrão do DANFE (dados do emitente, destinatário,
 * chave de acesso, QR Code da NFC-e quando aplicável, tabela de itens e
 * cálculo de impostos). Este service já injeta todos os dados necessários.
 */
class DanfeService
{
    public function __construct(
        private readonly DanfeLogoService $logo,
        private readonly DanfeBarcodeService $barcode,
        private readonly DanfeQrCodeService $qrCode,
        private readonly DanfeTaxTransparencyService $taxTransparency,
    ) {}

    public function gerar(NotaFiscal $nfe): string
    {
        $nfe->loadMissing(['itens', 'empresa']);

        $pdf = Pdf::loadView('fiscal.danfe', [
            'nfe' => $nfe,
            'itens' => $nfe->itens,
            'danfeLogoDataUri' => $this->logo->dataUri($nfe->empresa),
            'danfeBarcodeDataUri' => $this->barcode->dataUri($nfe->chave_acesso),
            'danfeQrCodeDataUri' => (string) $nfe->modelo === '65'
                ? $this->qrCode->dataUri($nfe)
                : $this->qrCode->dataUriConsultaNfe($nfe),
            'danfeQrCodeUrl' => (string) $nfe->modelo === '65'
                ? $this->qrCode->metadata($nfe)['qr_code_url']
                : $this->qrCode->consultaNfeUrl($nfe),
            'danfeTributos' => $this->taxTransparency->calculate($nfe),
        ])->setPaper('a4', 'portrait');

        $caminho = "danfe/{$nfe->empresa_id}/{$nfe->chave_acesso}.pdf";

        $disk = (string) config('services.sefaz.storage_disk', 'local');
        $storage = Storage::disk($disk);
        if (! $storage->put($caminho, $pdf->output())) {
            throw new \RuntimeException('Não foi possível armazenar o DANFE. Verifique FISCAL_STORAGE_DISK.');
        }

        return $disk === 'local'
            ? route('fiscal.danfe.file', ['id' => $nfe->id])
            : $storage->url($caminho);
    }
}
