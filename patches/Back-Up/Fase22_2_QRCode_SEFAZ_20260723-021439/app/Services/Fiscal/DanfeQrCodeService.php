<?php

namespace App\Services\Fiscal;

use App\Models\NotaFiscal;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;

class DanfeQrCodeService
{
    public function dataUri(NotaFiscal $nota): ?string
    {
        $conteudo = $this->conteudo($nota);

        if (! $conteudo) {
            return null;
        }

        if (! class_exists(Builder::class)) {
            Log::warning('Biblioteca endroid/qr-code não instalada; DANFE gerado sem QR Code.', [
                'nota_fiscal_id' => $nota->id,
            ]);

            return null;
        }

        try {
            $resultado = new Builder(
                writer: new PngWriter(),
                writerOptions: [],
                validateResult: false,
                data: $conteudo,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Medium,
                size: 280,
                margin: 8,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
            );

            return $resultado->build()->getDataUri();
        } catch (\Throwable $e) {
            Log::warning('Não foi possível gerar o QR Code do DANFE.', [
                'nota_fiscal_id' => $nota->id,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function conteudo(NotaFiscal $nota): ?string
    {
        $url = trim((string) $nota->qr_code_url);
        if ($url !== '') {
            return html_entity_decode($url, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $xml = trim((string) $nota->xml);
        if ($xml !== '') {
            libxml_use_internal_errors(true);
            $documento = simplexml_load_string($xml);
            if ($documento !== false) {
                $namespaces = $documento->getNamespaces(true);
                $nfe = isset($namespaces['']) ? $documento->children($namespaces['']) : $documento;
                $resultado = $nfe->xpath('//*[local-name()="qrCode"]');
                if (! empty($resultado)) {
                    $valor = trim((string) $resultado[0]);
                    if ($valor !== '') {
                        return html_entity_decode($valor, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                }
            }
            libxml_clear_errors();
        }

        $consulta = trim((string) $nota->url_chave);
        return $consulta !== '' ? $consulta : null;
    }
}
