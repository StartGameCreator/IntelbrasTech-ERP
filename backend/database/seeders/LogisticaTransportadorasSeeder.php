<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogisticaTransportadorasSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['slug' => 'correios', 'nome' => 'Correios', 'tipo' => 'nacional'],
            ['slug' => 'melhor_envio', 'nome' => 'Melhor Envio', 'tipo' => 'hub'],
            ['slug' => 'intelipost', 'nome' => 'Intelipost', 'tipo' => 'hub'],
            ['slug' => 'frenet', 'nome' => 'Frenet', 'tipo' => 'hub'],
            ['slug' => 'jadlog', 'nome' => 'Jadlog', 'tipo' => 'transportadora'],
            ['slug' => 'loggi', 'nome' => 'Loggi', 'tipo' => 'transportadora'],
            ['slug' => 'azul_cargo', 'nome' => 'Azul Cargo', 'tipo' => 'transportadora'],
            ['slug' => 'braspress', 'nome' => 'Braspress', 'tipo' => 'transportadora'],
            ['slug' => 'jamef', 'nome' => 'Jamef', 'tipo' => 'transportadora'],
            ['slug' => 'total_express', 'nome' => 'Total Express', 'tipo' => 'transportadora'],
            ['slug' => 'rodonaves', 'nome' => 'Rodonaves', 'tipo' => 'transportadora'],
            ['slug' => 'sequoia', 'nome' => 'Sequoia', 'tipo' => 'transportadora'],
            ['slug' => 'kangu', 'nome' => 'Kangu', 'tipo' => 'hub'],
            ['slug' => 'lalamove', 'nome' => 'Lalamove', 'tipo' => 'entrega_rapida'],
            ['slug' => 'uber_direct', 'nome' => 'Uber Direct', 'tipo' => 'entrega_rapida'],
        ];

        foreach ($items as $index => $item) {
            DB::table('logistica_transportadoras')->updateOrInsert(
                ['slug' => $item['slug']],
                [
                    'nome' => $item['nome'],
                    'tipo' => $item['tipo'],
                    'ativo' => true,
                    'ordem' => $index + 1,
                    'recursos' => json_encode([
                        'cotacao' => true,
                        'etiqueta' => true,
                        'rastreio' => true,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
