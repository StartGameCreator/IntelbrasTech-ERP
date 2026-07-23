<?php

namespace App\Services\Fiscal;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ContadorExportacaoService
{
    public function gerar(int $empresaId, int $usuarioId, string $tipo, string $inicio, string $fim): array
    {
        $tipos = $this->tiposDisponiveis();

        if (!isset($tipos[$tipo])) {
            throw new RuntimeException('Tipo de exportação inválido.');
        }

        $slug = Str::slug($tipo);
        $timestamp = now()->format('Ymd-His');
        $directory = "contador/empresa-{$empresaId}/{$timestamp}";
        Storage::disk('local')->makeDirectory($directory);

        $files = match ($tipo) {
            'xml-nfce-saida' => $this->exportarXmlFiscal($directory, 'nfce', $inicio, $fim),
            'xml-nfe-saida' => $this->exportarXmlFiscal($directory, 'nfe-saida', $inicio, $fim),
            'xml-nfe-entrada' => $this->exportarXmlFiscal($directory, 'nfe-entrada', $inicio, $fim),
            'estoque' => [$this->exportarTabelaCsv($directory, 'estoque', $this->estoqueRows(), $inicio, $fim)],
            'contas-pagar' => [$this->exportarTabelaCsv($directory, 'contas-pagar', $this->financeiroRows('pagar'), $inicio, $fim)],
            'contas-receber' => [$this->exportarTabelaCsv($directory, 'contas-receber', $this->financeiroRows('receber'), $inicio, $fim)],
            'pagamentos' => [$this->exportarTabelaCsv($directory, 'pagamentos', $this->financeiroRows('pagamentos'), $inicio, $fim)],
            'recebimentos' => [$this->exportarTabelaCsv($directory, 'recebimentos', $this->financeiroRows('recebimentos'), $inicio, $fim)],
            'nfse' => [$this->exportarTabelaCsv($directory, 'nfse', $this->fiscalRows('nfse'), $inicio, $fim)],
            'nfe-entrada' => [$this->exportarTabelaCsv($directory, 'nfe-entrada', $this->fiscalRows('entrada'), $inicio, $fim)],
            'nfe-saida' => [$this->exportarTabelaCsv($directory, 'nfe-saida', $this->fiscalRows('saida'), $inicio, $fim)],
            'nfce' => [$this->exportarTabelaCsv($directory, 'nfce', $this->fiscalRows('nfce'), $inicio, $fim)],
            'sped-fiscal' => [$this->exportarLeiautePlaceholder($directory, 'sped-fiscal', $inicio, $fim)],
            'sintegra' => [$this->exportarLeiautePlaceholder($directory, 'sintegra', $inicio, $fim)],
            default => [],
        };

        $zipRelative = "{$directory}/intelbrastech-{$slug}-{$timestamp}.zip";
        $zipAbsolute = Storage::disk('local')->path($zipRelative);
        $zip = new ZipArchive();

        if ($zip->open($zipAbsolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo ZIP.');
        }

        foreach ($files as $relative) {
            if ($relative && Storage::disk('local')->exists($relative)) {
                $zip->addFile(Storage::disk('local')->path($relative), basename($relative));
            }
        }

        $zip->close();

        $id = DB::table('contador_exportacoes')->insertGetId([
            'empresa_id' => $empresaId,
            'tipo' => $tipo,
            'formato' => 'zip',
            'periodo_inicio' => $inicio,
            'periodo_fim' => $fim,
            'status' => 'processado',
            'nome_arquivo' => basename($zipRelative),
            'caminho_arquivo' => $zipRelative,
            'tamanho_bytes' => filesize($zipAbsolute) ?: 0,
            'gerado_por' => $usuarioId,
            'gerado_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $id,
            'nome_arquivo' => basename($zipRelative),
            'tamanho_bytes' => filesize($zipAbsolute) ?: 0,
        ];
    }

    public function tiposDisponiveis(): array
    {
        return [
            'xml-nfce-saida' => 'XML NFC-e de saída',
            'xml-nfe-saida' => 'XML NF-e de saída',
            'xml-nfe-entrada' => 'XML NF-e de entrada',
            'estoque' => 'Relatório de estoque',
            'contas-pagar' => 'Contas a pagar',
            'contas-receber' => 'Contas a receber',
            'pagamentos' => 'Pagamentos',
            'recebimentos' => 'Recebimentos',
            'nfse' => 'NFS-e',
            'nfe-entrada' => 'NF-e Entrada',
            'nfe-saida' => 'NF-e Saída',
            'nfce' => 'NFC-e',
            'sped-fiscal' => 'Arquivo auxiliar do SPED Fiscal',
            'sintegra' => 'Arquivo Sintegra',
        ];
    }

    private function exportarXmlFiscal(string $directory, string $tipo, string $inicio, string $fim): array
    {
        $files = [];
        $tables = ['documentos_fiscais', 'notas_fiscais', 'nfe_documentos'];

        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);

            foreach (['data_emissao', 'emitida_em', 'created_at'] as $dateColumn) {
                if (Schema::hasColumn($table, $dateColumn)) {
                    $query->whereDate($dateColumn, '>=', $inicio)->whereDate($dateColumn, '<=', $fim);
                    break;
                }
            }

            if (Schema::hasColumn($table, 'tipo')) {
                $query->where('tipo', 'like', '%' . str_replace(['-saida', '-entrada'], '', $tipo) . '%');
            }

            $rows = $query->limit(5000)->get();

            foreach ($rows as $index => $row) {
                $array = (array) $row;
                $xml = $array['xml'] ?? $array['xml_autorizado'] ?? $array['conteudo_xml'] ?? null;

                if (!$xml) {
                    continue;
                }

                $key = $array['chave_acesso'] ?? $array['chave'] ?? ($index + 1);
                $relative = "{$directory}/{$tipo}-{$key}.xml";
                Storage::disk('local')->put($relative, $xml);
                $files[] = $relative;
            }

            if ($files) {
                break;
            }
        }

        if (!$files) {
            $relative = "{$directory}/LEIA-ME-{$tipo}.txt";
            Storage::disk('local')->put(
                $relative,
                "Nenhum XML do tipo {$tipo} foi encontrado entre {$inicio} e {$fim}.\r\n"
            );
            $files[] = $relative;
        }

        return $files;
    }

    private function estoqueRows(): array
    {
        if (!Schema::hasTable('produtos')) {
            return [];
        }

        $query = DB::table('produtos');

        $columns = array_values(array_filter([
            Schema::hasColumn('produtos', 'id') ? 'id' : null,
            Schema::hasColumn('produtos', 'sku') ? 'sku' : null,
            Schema::hasColumn('produtos', 'codigo') ? 'codigo' : null,
            Schema::hasColumn('produtos', 'nome') ? 'nome' : null,
            Schema::hasColumn('produtos', 'estoque_atual') ? 'estoque_atual' : null,
            Schema::hasColumn('produtos', 'estoque_minimo') ? 'estoque_minimo' : null,
            Schema::hasColumn('produtos', 'preco_custo') ? 'preco_custo' : null,
            Schema::hasColumn('produtos', 'ncm') ? 'ncm' : null,
        ]));

        return $query->select($columns ?: ['*'])->limit(10000)->get()->map(fn ($r) => (array) $r)->all();
    }

    private function financeiroRows(string $tipo): array
    {
        $possible = match ($tipo) {
            'pagar', 'pagamentos' => ['contas_pagar', 'financeiro_contas_pagar'],
            default => ['contas_receber', 'financeiro_contas_receber'],
        };

        foreach ($possible as $table) {
            if (Schema::hasTable($table)) {
                return DB::table($table)->limit(10000)->get()->map(fn ($r) => (array) $r)->all();
            }
        }

        return [];
    }

    private function fiscalRows(string $tipo): array
    {
        foreach (['documentos_fiscais', 'notas_fiscais', 'nfe_documentos'] as $table) {
            if (Schema::hasTable($table)) {
                $query = DB::table($table);

                if (Schema::hasColumn($table, 'tipo')) {
                    $query->where('tipo', 'like', '%' . $tipo . '%');
                }

                return $query->limit(10000)->get()->map(fn ($r) => (array) $r)->all();
            }
        }

        return [];
    }

    private function exportarTabelaCsv(string $directory, string $name, array $rows, string $inicio, string $fim): string
    {
        $relative = "{$directory}/{$name}-{$inicio}-{$fim}.csv";
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, "\xEF\xBB\xBF");

        if ($rows) {
            fputcsv($handle, array_keys($rows[0]), ';');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    fn ($value) => is_array($value) || is_object($value)
                        ? json_encode($value, JSON_UNESCAPED_UNICODE)
                        : $value,
                    $row
                ), ';');
            }
        } else {
            fputcsv($handle, ['mensagem', 'periodo_inicio', 'periodo_fim'], ';');
            fputcsv($handle, ['Nenhum registro encontrado.', $inicio, $fim], ';');
        }

        rewind($handle);
        Storage::disk('local')->put($relative, stream_get_contents($handle));
        fclose($handle);

        return $relative;
    }

    private function exportarLeiautePlaceholder(string $directory, string $tipo, string $inicio, string $fim): string
    {
        $relative = "{$directory}/{$tipo}-{$inicio}-{$fim}.txt";
        $conteudo = implode("\r\n", [
            '|0000|INTELBRASTECH ERP|' . strtoupper($tipo) . '|',
            '|PERIODO|' . $inicio . '|' . $fim . '|',
            '|AVISO|Arquivo auxiliar gerado pelo ERP. Validar o leiaute com o contador antes da entrega oficial.|',
            '|9999|FIM|',
        ]);

        Storage::disk('local')->put($relative, $conteudo . "\r\n");

        return $relative;
    }
}
