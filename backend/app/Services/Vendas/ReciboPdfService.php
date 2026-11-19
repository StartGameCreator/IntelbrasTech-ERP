<?php

namespace App\Services\Vendas;

use App\Models\Recibo;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Gera o PDF do Recibo (comprovante não fiscal) em formato de bobina
 * térmica de 80mm, pronto para impressão em impressoras térmicas comuns
 * de balcão (a mesma usada para NFC-e/cupom, mas aqui sem qualquer
 * informação fiscal — só controle interno de venda/garantia/entrega).
 */
class ReciboPdfService
{
    public function gerar(Recibo $recibo): string
    {
        $recibo->loadMissing('empresa', 'pedido');

        $pdf = Pdf::loadView('recibo.termico', [
            'recibo'  => $recibo,
            'empresa' => $recibo->empresa,
        ])->setPaper([0, 0, 226.77, 700], 'portrait'); // ~80mm de largura

        $caminho = "recibos/{$recibo->empresa_id}/{$recibo->numero}.pdf";

        Storage::disk('s3')->put($caminho, $pdf->output(), 'public');

        return Storage::disk('s3')->url($caminho);
    }
}
