<?php

namespace App\Services\Expedicao;

use App\Models\EtiquetaEnvio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class EtiquetaEnvioService
{
    private const DISK = 'local';

    public function armazenarUpload(object $expedicao, UploadedFile $arquivo, array $dados, int $usuarioId): EtiquetaEnvio
    {
        $extensao = strtolower($arquivo->getClientOriginalExtension());
        if (! in_array($extensao, ['pdf', 'zpl', 'png'], true)) {
            throw new RuntimeException('Formato não suportado. Envie PDF, ZPL ou PNG.');
        }

        $conteudo = file_get_contents($arquivo->getRealPath());
        if ($conteudo === false || $conteudo === '') {
            throw new RuntimeException('O arquivo da etiqueta está vazio ou não pôde ser lido.');
        }

        return $this->salvar(
            $expedicao,
            $conteudo,
            $extensao,
            $arquivo->getClientOriginalName(),
            $arquivo->getMimeType(),
            array_merge($dados, ['origem' => 'upload']),
            $usuarioId
        );
    }

    public function gerarPadrao(object $expedicao, object $pedido, array $dados, int $usuarioId): EtiquetaEnvio
    {
        $destinatario = [
            'nome' => $dados['destinatario_nome'] ?? optional($pedido->cliente)->nome,
            'logradouro' => $pedido->entrega_logradouro,
            'numero' => $pedido->entrega_numero,
            'complemento' => $pedido->entrega_complemento,
            'bairro' => $pedido->entrega_bairro,
            'cidade' => $pedido->entrega_cidade,
            'uf' => $pedido->entrega_estado,
            'cep' => $pedido->entrega_cep,
        ];

        $payload = [
            'expedicao' => $expedicao,
            'pedido' => $pedido,
            'destinatario' => $destinatario,
            'codigo_rastreio' => $dados['codigo_rastreio'] ?? $pedido->codigo_rastreio,
            'transportadora' => $dados['transportadora'] ?? null,
            'servico' => $dados['servico'] ?? null,
            'volume_numero' => $dados['volume_numero'] ?? 1,
            'total_volumes' => $dados['total_volumes'] ?? max(1, (int) $expedicao->total_volumes),
            'marketplace' => $dados['marketplace'] ?? $pedido->marketplace ?? $pedido->canal,
        ];

        $pdf = Pdf::loadView('expedicao.etiqueta-100x150', $payload)
            ->setPaper([0, 0, 283.4646, 425.1969], 'portrait');

        return $this->salvar(
            $expedicao,
            $pdf->output(),
            'pdf',
            "etiqueta-{$expedicao->codigo}.pdf",
            'application/pdf',
            array_merge($dados, ['origem' => 'gerada_erp']),
            $usuarioId
        );
    }

    private function salvar(
        object $expedicao,
        string $conteudo,
        string $formato,
        string $nomeOriginal,
        ?string $mime,
        array $dados,
        int $usuarioId
    ): EtiquetaEnvio {
        return DB::transaction(function () use (
            $expedicao, $conteudo, $formato, $nomeOriginal, $mime, $dados, $usuarioId
        ) {
            $hash = hash('sha256', $conteudo);
            $existente = EtiquetaEnvio::query()
                ->where('empresa_id', $expedicao->empresa_id)
                ->where('arquivo_hash', $hash)
                ->where('expedicao_id', $expedicao->id)
                ->first();

            if ($existente) {
                return $existente;
            }

            $nome = now()->format('Y/m').'/'.$expedicao->empresa_id.'/'.$expedicao->id.'/'
                .Str::uuid().'.'.$formato;
            $path = 'etiquetas-envio/'.$nome;

            Storage::disk(self::DISK)->put($path, $conteudo);

            return EtiquetaEnvio::create([
                'empresa_id' => $expedicao->empresa_id,
                'expedicao_id' => $expedicao->id,
                'pedido_id' => $expedicao->pedido_id,
                'volume_id' => $dados['volume_id'] ?? null,
                'marketplace' => $dados['marketplace'] ?? null,
                'transportadora' => $dados['transportadora'] ?? null,
                'servico' => $dados['servico'] ?? null,
                'external_shipment_id' => $dados['external_shipment_id'] ?? null,
                'codigo_rastreio' => $dados['codigo_rastreio'] ?? null,
                'origem' => $dados['origem'] ?? 'upload',
                'formato' => $formato,
                'tamanho_papel' => $dados['tamanho_papel'] ?? '100x150',
                'nome_original' => $nomeOriginal,
                'arquivo_path' => $path,
                'arquivo_hash' => $hash,
                'arquivo_tamanho' => strlen($conteudo),
                'mime_type' => $mime,
                'status' => 'disponivel',
                'gerada_em' => now(),
                'criado_por' => $usuarioId,
                'metadata' => $dados['metadata'] ?? null,
            ]);
        });
    }

    public function registrarImpressao(EtiquetaEnvio $etiqueta, int $usuarioId, array $dados): EtiquetaEnvio
    {
        return DB::transaction(function () use ($etiqueta, $usuarioId, $dados) {
            $copias = max(1, (int) ($dados['copias'] ?? 1));

            DB::table('historico_impressoes_etiquetas')->insert([
                'etiqueta_envio_id' => $etiqueta->id,
                'empresa_id' => $etiqueta->empresa_id,
                'impressora_id' => $dados['impressora_id'] ?? null,
                'usuario_id' => $usuarioId,
                'copias' => $copias,
                'modo' => $dados['modo'] ?? 'navegador',
                'status' => 'registrada',
                'created_at' => now(),
            ]);

            $etiqueta->increment('quantidade_impressoes', $copias);
            $etiqueta->update([
                'status' => 'impressa',
                'ultima_impressao_em' => now(),
                'ultimo_impresso_por' => $usuarioId,
            ]);

            return $etiqueta->fresh();
        });
    }
}
