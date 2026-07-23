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

    private function gerarDataUri(string $url): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(300)
            ->margin(12)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return $result->getDataUri();
    }

    private function urlValida(?string $url): ?string
    {
        $url = html_entity_decode(trim((string) $url), ENT_QUOTES | ENT_XML1, 'UTF-8');
        return $url !== '' && preg_match('#^https?://#i', $url) ? $url : null;
    }
}
