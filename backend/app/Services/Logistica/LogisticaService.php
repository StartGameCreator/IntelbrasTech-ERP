<?php

namespace App\Services\Logistica;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class LogisticaService
{
    public function cotar(int $empresaId, array $data): array
    {
        $providers = DB::table('logistica_transportadoras')
            ->where('ativo', true)
            ->orderBy('ordem')
            ->get();

        $peso = max(0.1, (float) $data['peso']);
        $volume = max(
            1,
            ((float) $data['altura'] * (float) $data['largura'] * (float) $data['comprimento']) / 6000
        );
        $pesoCubado = max($peso, $volume);

        $base = 12 + ($pesoCubado * 2.8);
        $distanceFactor = $this->distanceFactor($data['cep_origem'], $data['cep_destino']);

        $results = [];

        foreach ($providers as $index => $provider) {
            $factor = 1 + (($index % 7) * 0.06);
            $price = round(($base * $distanceFactor * $factor) + (($index % 3) * 2.5), 2);
            $days = max(1, (int) ceil(2 + ($distanceFactor * 2) + ($index % 5)));

            $results[] = [
                'transportadora_id' => $provider->id,
                'transportadora' => $provider->nome,
                'slug' => $provider->slug,
                'servico' => $provider->tipo === 'entrega_rapida' ? 'Entrega expressa' : 'Econômico',
                'valor' => $price,
                'prazo_dias' => $days,
                'estimativa' => true,
            ];
        }

        usort($results, fn ($a, $b) => $a['valor'] <=> $b['valor']);

        DB::table('logistica_cotacoes')->insert([
            'empresa_id' => $empresaId,
            'pedido_id' => $data['pedido_id'] ?? null,
            'expedicao_id' => $data['expedicao_id'] ?? null,
            'cep_origem' => preg_replace('/\D+/', '', $data['cep_origem']),
            'cep_destino' => preg_replace('/\D+/', '', $data['cep_destino']),
            'peso' => $data['peso'],
            'altura' => $data['altura'],
            'largura' => $data['largura'],
            'comprimento' => $data['comprimento'],
            'valor_declarado' => $data['valor_declarado'] ?? 0,
            'status' => 'processada',
            'resultados' => json_encode($results, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $results;
    }

    public function criarEnvio(int $empresaId, array $data): int
    {
        $provider = DB::table('logistica_transportadoras')
            ->where('id', $data['transportadora_id'])
            ->where('ativo', true)
            ->first();

        if (!$provider) {
            throw new RuntimeException('Transportadora não encontrada.');
        }

        $id = DB::table('logistica_envios')->insertGetId([
            'empresa_id' => $empresaId,
            'pedido_id' => $data['pedido_id'] ?? null,
            'expedicao_id' => $data['expedicao_id'] ?? null,
            'transportadora_id' => $provider->id,
            'servico' => $data['servico'] ?? 'Econômico',
            'status' => 'aguardando_postagem',
            'valor_frete' => $data['valor_frete'] ?? 0,
            'prazo_dias' => $data['prazo_dias'] ?? null,
            'dados' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('logistica_rastreios')->insert([
            'envio_id' => $id,
            'status' => 'aguardando_postagem',
            'descricao' => 'Envio criado no ERP.',
            'created_at' => now(),
        ]);

        return $id;
    }

    public function registrarPostagem(int $empresaId, int $envioId, array $data): void
    {
        $envio = DB::table('logistica_envios')
            ->where('id', $envioId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$envio) {
            throw new RuntimeException('Envio não encontrado.');
        }

        DB::transaction(function () use ($envio, $envioId, $data) {
            DB::table('logistica_envios')->where('id', $envioId)->update([
                'codigo_rastreio' => $data['codigo_rastreio'],
                'status' => 'postado',
                'postado_em' => now(),
                'updated_at' => now(),
            ]);

            DB::table('logistica_rastreios')->insert([
                'envio_id' => $envioId,
                'status' => 'postado',
                'descricao' => 'Objeto postado.',
                'local' => $data['local'] ?? null,
                'evento_em' => now(),
                'created_at' => now(),
            ]);

            if ($envio->pedido_id) {
                DB::table('pedidos')->where('id', $envio->pedido_id)->update([
                    'status_entrega' => 'postado',
                    'codigo_rastreio' => $data['codigo_rastreio'],
                    'updated_at' => now(),
                ]);
            }

            if ($envio->expedicao_id) {
                DB::table('expedicoes')->where('id', $envio->expedicao_id)->update([
                    'status' => 'postado',
                    'postado_em' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function distanceFactor(string $origin, string $destination): float
    {
        $o = (int) substr(preg_replace('/\D+/', '', $origin), 0, 2);
        $d = (int) substr(preg_replace('/\D+/', '', $destination), 0, 2);
        return 1 + (abs($o - $d) / 40);
    }
}
