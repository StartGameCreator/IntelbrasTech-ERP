<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class VerificarEstruturaProdutos extends Command
{
    protected $signature = 'produtos:verificar-estrutura';
    protected $description = 'Verifica models e tabelas do módulo de produtos.';

    public function handle(): int
    {
        $erros = [];

        $checks = [
            [\App\Models\ProdutoVariacao::class, 'produto_variacoes', true],
            [\App\Models\ProdutoImagem::class, 'produto_imagens', false],
            [\App\Models\ProdutoCodigo::class, 'produto_codigos', false],
            [\App\Models\ProdutoListaPreco::class, 'produto_listas_preco', false],
            [\App\Models\ProdutoDocumento::class, 'produto_documentos', false],
        ];

        foreach ($checks as [$model, $table, $softDeletes]) {
            if (!class_exists($model)) {
                $erros[] = "Model ausente: {$model}";
                continue;
            }

            if (!Schema::hasTable($table)) {
                $erros[] = "Tabela ausente: {$table}";
                continue;
            }

            if ($softDeletes && !Schema::hasColumn($table, 'deleted_at')) {
                $erros[] = "A tabela {$table} usa SoftDeletes, mas não possui deleted_at.";
            }
        }

        $this->info('Verificação do módulo Produtos');

        if ($erros) {
            foreach ($erros as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        $this->info('Estrutura de produtos validada com sucesso.');
        return self::SUCCESS;
    }
}
