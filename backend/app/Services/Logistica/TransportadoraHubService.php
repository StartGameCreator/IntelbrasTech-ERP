<?php

namespace App\Services\Logistica;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransportadoraHubService
{
    private array $catalogo = [
        ['slug' => 'correios', 'nome' => 'Correios'],
        ['slug' => 'jadlog', 'nome' => 'Jadlog'],
        ['slug' => 'loggi', 'nome' => 'Loggi'],
        ['slug' => 'melhor_envio', 'nome' => 'Melhor Envio'],
        ['slug' => 'intelipost', 'nome' => 'Intelipost'],
        ['slug' => 'frenet', 'nome' => 'Frenet'],
        ['slug' => 'azul_cargo', 'nome' => 'Azul Cargo'],
        ['slug' => 'braspress', 'nome' => 'Braspress'],
        ['slug' => 'jamef', 'nome' => 'Jamef'],
        ['slug' => 'total_express', 'nome' => 'Total Express'],
    ];

    public function garantirCatalogo(int $empresaId): void
    {
        foreach ($this->catalogo as $item) {
            DB::table('transportadoras_integracoes')->updateOrInsert(
                ['empresa_id' => $empresaId, 'slug' => $item['slug']],
                [
                    'nome' => $item['nome'],
                    'tipo' => 'transportadora',
                    'status' => DB::raw("COALESCE(status, 'desconectado')"),
                    'ativo' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function cotar(int $empresaId, array $dados): array
    {
        $this->garantirCatalogo($empresaId);

        $transportadoras = DB::table('transportadoras_integracoes')
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->get();

        if ($transportadoras->isEmpty()) {
            throw new RuntimeException('Nenhuma transportadora disponível.');
        }

        $peso = max(0.1, (float) ($dados['peso'] ?? 0.1));
        $distanciaBase = $this->distanciaCep(
            (string) $dados['cep_origem'],
            (string) $dados['cep_destino']
        );

        $fatores = [
            'correios' => [1.00, 5],
            'jadlog' => [0.94, 4],
            'loggi' => [1.18, 2],
            'melhor_envio' => [0.88, 6],
            'intelipost' => [0.92, 5],
            'frenet' => [0.96, 5],
            'azul_cargo' => [1.15, 3],
            'braspress' => [0.90, 7],
            'jamef' => [0.93, 6],
            'total_express' => [0.89, 6],
        ];

        $resultados = [];

        foreach ($transportadoras as $transportadora) {
            [$fator, $prazoBase] = $fatores[$transportadora->slug] ?? [1.0, 6];

            $valor = round(
                (12.90 + ($peso * 2.35) + ($distanciaBase * 0.018)) * $fator,
                2
            );

            $prazo = max(1, (int) ceil($prazoBase + ($distanciaBase / 900)));

            $id = DB::table('cotacoes_frete')->insertGetId([
                'empresa_id' => $empresaId,
                'expedicao_id' => $dados['expedicao_id'] ?? null,
                'pedido_id' => $dados['pedido_id'] ?? null,
                'cep_origem' => preg_replace('/\D+/', '', (string) $dados['cep_origem']),
                'cep_destino' => preg_replace('/\D+/', '', (string) $dados['cep_destino']),
                'peso' => $peso,
                'altura' => $dados['altura'] ?? null,
                'largura' => $dados['largura'] ?? null,
                'comprimento' => $dados['comprimento'] ?? null,
                'transportadora_slug' => $transportadora->slug,
                'servico_codigo' => strtoupper($transportadora->slug) . '-PADRAO',
                'servico_nome' => $transportadora->nome . ' Padrão',
                'valor' => $valor,
                'prazo_dias' => $prazo,
                'status' => 'cotado',
                'dados' => json_encode([
                    'simulacao' => true,
                    'integracao_real' => $transportadora->status === 'conectado',
                ], JSON_UNESCAPED_UNICODE),
                'cotado_em' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $resultados[] = [
                'id' => $id,
                'transportadora_slug' => $transportadora->slug,
                'transportadora_nome' => $transportadora->nome,
                'servico_nome' => $transportadora->nome . ' Padrão',
                'valor' => $valor,
                'prazo_dias' => $prazo,
                'conectado' => $transportadora->status === 'conectado',
            ];
        }

        usort($resultados, fn ($a, $b) => $a['valor'] <=> $b['valor']);

        return $resultados;
    }

    public function contratar(int $empresaId, int $cotacaoId): object
    {
        return DB::transaction(function () use ($empresaId, $cotacaoId) {
            $cotacao = DB::table('cotacoes_frete')
                ->where('id', $cotacaoId)
                ->where('empresa_id', $empresaId)
                ->first();

            if (!$cotacao) {
                throw new RuntimeException('Cotação não encontrada.');
            }

            $existente = DB::table('envios_logisticos')
                ->where('empresa_id', $empresaId)
                ->where('cotacao_id', $cotacaoId)
                ->first();

            if ($existente) {
                return $existente;
            }

            $codigo = 'IT' . now()->format('ymdHis') . str_pad((string) $cotacaoId, 4, '0', STR_PAD_LEFT);

            $envioId = DB::table('envios_logisticos')->insertGetId([
                'empresa_id' => $empresaId,
                'expedicao_id' => $cotacao->expedicao_id,
                'pedido_id' => $cotacao->pedido_id,
                'cotacao_id' => $cotacao->id,
                'transportadora_slug' => $cotacao->transportadora_slug,
                'servico_codigo' => $cotacao->servico_codigo,
                'servico_nome' => $cotacao->servico_nome,
                'codigo_rastreio' => $codigo,
                'status' => 'etiqueta_gerada',
                'valor_frete' => $cotacao->valor,
                'etiqueta_conteudo' => $this->etiquetaHtml($codigo, $cotacao),
                'dados' => json_encode(['simulacao' => true], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('cotacoes_frete')->where('id', $cotacaoId)->update([
                'status' => 'contratado',
                'updated_at' => now(),
            ]);

            DB::table('rastreamento_eventos')->insert([
                'envio_id' => $envioId,
                'codigo' => 'ETIQUETA_GERADA',
                'status' => 'etiqueta_gerada',
                'descricao' => 'Etiqueta gerada e envio aguardando postagem.',
                'ocorrido_em' => now(),
                'created_at' => now(),
            ]);

            return DB::table('envios_logisticos')->where('id', $envioId)->first();
        });
    }

    public function atualizarStatus(
        int $empresaId,
        int $envioId,
        string $status,
        ?string $descricao = null
    ): void {
        $envio = DB::table('envios_logisticos')
            ->where('id', $envioId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$envio) {
            throw new RuntimeException('Envio não encontrado.');
        }

        $updates = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if ($status === 'postado') {
            $updates['postado_em'] = now();
        }

        if ($status === 'entregue') {
            $updates['entregue_em'] = now();
        }

        DB::table('envios_logisticos')->where('id', $envioId)->update($updates);

        DB::table('rastreamento_eventos')->insert([
            'envio_id' => $envioId,
            'codigo' => strtoupper($status),
            'status' => $status,
            'descricao' => $descricao ?: 'Status atualizado para ' . $status . '.',
            'ocorrido_em' => now(),
            'created_at' => now(),
        ]);

        if ($envio->expedicao_id && $status === 'postado') {
            DB::table('expedicoes')->where('id', $envio->expedicao_id)->update([
                'status' => 'expedida',
                'expedida_em' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function distanciaCep(string $origem, string $destino): int
    {
        $a = (int) substr(preg_replace('/\D+/', '', $origem) . '00000000', 0, 5);
        $b = (int) substr(preg_replace('/\D+/', '', $destino) . '00000000', 0, 5);

        return max(50, abs($a - $b) / 8);
    }

    private function etiquetaHtml(string $codigo, object $cotacao): string
    {
        return '<div style="font-family:Arial;width:360px;border:2px solid #000;padding:14px">'
            . '<h2 style="margin:0">ETIQUETA DE ENVIO</h2>'
            . '<p><strong>' . e($cotacao->servico_nome) . '</strong></p>'
            . '<p>Destino: ' . e($cotacao->cep_destino) . '</p>'
            . '<div style="font-family:monospace;font-size:24px;font-weight:bold;letter-spacing:2px">'
            . e($codigo)
            . '</div>'
            . '<p>Peso: ' . number_format((float) $cotacao->peso, 3, ',', '.') . ' kg</p>'
            . '</div>';
    }
}
