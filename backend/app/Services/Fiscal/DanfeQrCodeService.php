<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class DanfeQrCodeService
{
    public function __construct(private readonly NfceXmlSupplementService $xmlSupplement) {}

    public function metadata(NotaFiscal $nota): array
    {
        $extraido = $this->xmlSupplement->extrair($nota->xml);
        $qrUrl = $extraido['qr_code_url'] ?: $this->urlValida($nota->qr_code_url);
        $consultaUrl = $extraido['url_chave'] ?: $this->urlValida($nota->url_chave);

        return [
            'qr_code_url' => $qrUrl,
            'consulta_url' => $consultaUrl,
            'data_uri' => $qrUrl ? $this->gerarDataUri($qrUrl) : null,
        ];
    }

    public function dataUri(NotaFiscal $nota): ?string
    {
        return $this->metadata($nota)['data_uri'];
    }

    /** Gera um QR Code genérico, usado no DANFE da NF-e para representar a chave de acesso. */
    public function dataUriFromText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $this->gerarDataUri($value) : null;
    }

    public function consultaNfeUrl(NotaFiscal $nota): ?string
    {
        $chave = preg_replace('/\D/', '', (string) $nota->chave_acesso);
        if (strlen($chave) !== 44) {
            return null;
        }

        // url_chave pertence ao suplemento da NFC-e e pode apontar para uma
        // tela que exige o parâmetro assinado `p`. Uma NF-e modelo 55 jamais
        // deve reaproveitar essa URL, mesmo que tenha sido gravada no registro.
        $base = $this->urlValida((string) config('services.sefaz.nfe_consulta_url'));

        if (! $base) {
            return null;
        }

        // Alguns portais estaduais oferecem uma URL parametrizada própria.
        // Nesses casos a configuração pode usar {chave}. O portal público de
        // Goiás exige CAPTCHA e não aceita consulta direta sem interação.
        return str_contains($base, '{chave}')
            ? str_replace('{chave}', $chave, $base)
            : $base;
    }

    public function dataUriConsultaNfe(NotaFiscal $nota): ?string
    {
        $url = $this->consultaNfeUrl($nota);

        return $url ? $this->gerarDataUri($url) : null;
    }

    private function gerarDataUri(string $url): string
    {
        /*
         * endroid/qr-code 6.x:
         * Builder::create() e os setters encadeados foram removidos.
         * As opções agora são fornecidas ao construtor.
         */
        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $builder->build()->getDataUri();
    }

    private function urlValida(?string $url): ?string
    {
        $url = html_entity_decode(trim((string) $url), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $url !== '' && preg_match('#^https?://#i', $url) ? $url : null;
    }
}
