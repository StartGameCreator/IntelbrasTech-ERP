<?php

namespace App\Services\Fiscal;

use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Log;

class DanfeBarcodeService
{
    public function dataUri(?string $chaveAcesso): ?string
    {
        $chave = preg_replace('/\D/', '', (string) $chaveAcesso);

        if (strlen($chave) !== 44) {
            return null;
        }

        if (! class_exists(BarcodeGeneratorPNG::class)) {
            Log::warning('Biblioteca picqer/php-barcode-generator não instalada; DANFE gerado sem código de barras.');
            return null;
        }

        try {
            $generator = new BarcodeGeneratorPNG();
            $png = $generator->getBarcode($chave, $generator::TYPE_CODE_128, 1.45, 42);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (\Throwable $e) {
            Log::warning('Não foi possível gerar o código de barras do DANFE.', [
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
