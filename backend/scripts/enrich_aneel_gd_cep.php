<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$csvPath = storage_path('app/aneel/empreendimento-geracao-distribuida.csv');
$dbPath = storage_path('app/aneel/gd.sqlite');

if (! is_file($csvPath) || ! is_file($dbPath)) {
    fwrite(STDERR, "CSV ou SQLite nao encontrado.\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA synchronous = OFF');
$pdo->exec('PRAGMA journal_mode = OFF');

$cols = $pdo->query('PRAGMA table_info(gd)')->fetchAll(PDO::FETCH_ASSOC);
$hasCep = in_array('cep', array_column($cols, 'name'), true);
if (! $hasCep) {
    $pdo->exec('ALTER TABLE gd ADD COLUMN cep TEXT');
}
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_gd_codigo ON gd (codigo)');

$stream = fopen($csvPath, 'rb');
if (! $stream) {
    fwrite(STDERR, "Nao foi possivel abrir CSV.\n");
    exit(1);
}

$header = readCsv($stream);
$codigoIndex = array_search('CodEmpreendimento', $header, true);
$cepIndex = array_search('CodCEP', $header, true);
if ($codigoIndex === false || $cepIndex === false) {
    fwrite(STDERR, "Colunas CodEmpreendimento/CodCEP nao encontradas.\n");
    exit(1);
}

$update = $pdo->prepare('UPDATE gd SET cep = :cep WHERE codigo = :codigo');
$pdo->beginTransaction();
$count = 0;
$updated = 0;

while (($row = readCsv($stream)) !== false) {
    if (! isset($row[$codigoIndex], $row[$cepIndex])) {
        continue;
    }

    $codigo = textValue((string) $row[$codigoIndex]);
    $cep = preg_replace('/\D/', '', textValue((string) $row[$cepIndex]));
    if ($codigo === '' || $cep === '') {
        continue;
    }

    $update->execute([':codigo' => $codigo, ':cep' => $cep]);
    $updated += $update->rowCount();
    $count++;

    if ($count % 10000 === 0) {
        $pdo->commit();
        echo "Processados {$count}, atualizados {$updated}...\n";
        $pdo->beginTransaction();
    }
}

$pdo->commit();
fclose($stream);
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_gd_cep ON gd (cep)');

echo "Pronto. {$updated} registros atualizados com CEP.\n";

function readCsv($stream): array|false
{
    $row = fgetcsv($stream, 0, ';');
    if ($row !== false && count($row) === 1 && str_contains((string) $row[0], ',')) {
        $row = str_getcsv((string) $row[0], ',');
    }

    if (is_array($row) && isset($row[0])) {
        $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
    }

    return $row;
}

function textValue(string $text): string
{
    if ($text === '') {
        return '';
    }

    if (! mb_check_encoding($text, 'UTF-8')) {
        return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    return $text;
}
