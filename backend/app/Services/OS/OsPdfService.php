<?php

namespace App\Services\OS;

use App\Models\OrdemServico;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Gera o comprovante/laudo em PDF de uma Ordem de Serviço (via de entrada do
 * equipamento ou via de conclusão) e devolve a URL do arquivo salvo no storage.
 *
 * Mesma dependência já usada pelo módulo Fiscal:
 *   composer require barryvdh/laravel-dompdf
 */
class OsPdfService
{
    public function gerar(OrdemServico $os): string
    {
        $os->loadMissing(['cliente', 'tecnico', 'status', 'pecas.produto', 'empresa']);

        $pdf = Pdf::loadView('os.ordem-servico', [
            'os'       => $os,
            'empresa'  => $os->empresa,
            'cliente'  => $os->cliente,
            'pecas'    => $os->pecas,
        ])->setPaper('a4', 'portrait');

        $caminho = "os/{$os->empresa_id}/{$os->numero}.pdf";

        Storage::disk('s3')->put($caminho, $pdf->output(), 'public');

        return Storage::disk('s3')->url($caminho);
    }
}
