<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketplaceCategoriasSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            'Técnico de Informática',
            'Técnico de TV',
            'Técnico Eletrotécnico',
            'Técnico de Automação Residencial',
            'Técnico de Automação Industrial',
            'Bombeiro Hidráulico',
            'Mestre de Obras',
            'Pedreiro',
            'Ladrilheiro',
            'Técnico de Segurança Eletrônica',
            'Eletricista',
            'Eletricista de Energia Solar',
            'Vendedor',
            'Técnico de Portões',
            'Técnico de Impressoras',
        ];

        foreach ($categorias as $nome) {
            DB::table('marketplace_categorias')->updateOrInsert(
                ['slug' => Str::slug($nome)],
                [
                    'nome' => $nome,
                    'descricao' => $nome . ' cadastrado no Marketplace IntelbrasTech.',
                    'ativo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
