<?php

namespace App\Services\Portal;

use App\Models\ContratoServico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ContratoServicoPdfService
{
    /**
     * Gera uma via imutável do termo aceito. O arquivo usa o disco privado
     * local deliberadamente: o acesso externo ocorre apenas pelo token do chamado.
     */
    public function gerar(ContratoServico $contrato): ContratoServico
    {
        $contrato->loadMissing(['cliente', 'chamado.empresa']);

        $html = view('contratos.servico', [
            'contrato' => $contrato,
            'chamado' => $contrato->chamado,
            'empresa' => $contrato->chamado->empresa,
            'cliente' => $contrato->cliente,
        ])->render();

        $arquivo = "contratos/{$contrato->empresa_id}/{$contrato->uuid}.pdf";
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        if (! Storage::disk('local')->put($arquivo, $pdf->output())) {
            throw new RuntimeException('Não foi possível gravar o contrato no armazenamento seguro.');
        }

        $contrato->update([
            'conteudo_html' => $html,
            'arquivo_disk' => 'local',
            'arquivo_path' => $arquivo,
        ]);

        return $contrato->fresh();
    }
}
