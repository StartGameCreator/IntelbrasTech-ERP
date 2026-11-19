<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dbPath = storage_path('app/aneel/gd.sqlite');
if (! is_file($dbPath)) {
    fwrite(STDERR, "SQLite nao encontrado: {$dbPath}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA synchronous = OFF');
$pdo->exec('PRAGMA journal_mode = OFF');

$select = $pdo->query('SELECT id, nome, titular, municipio, classe, porte, tipo_geracao, fonte, distribuidora, busca_norm FROM gd');
$update = $pdo->prepare(<<<SQL
UPDATE gd SET
    nome = :nome,
    titular = :titular,
    municipio = :municipio,
    municipio_norm = :municipio_norm,
    classe = :classe,
    classe_norm = :classe_norm,
    porte = :porte,
    porte_norm = :porte_norm,
    tipo_geracao = :tipo_geracao,
    tipo_norm = :tipo_norm,
    fonte = :fonte,
    distribuidora = :distribuidora,
    busca_norm = :busca_norm
WHERE id = :id
SQL);

$pdo->beginTransaction();
$count = 0;

while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
    $nome = textValue((string) $row['nome']);
    $titular = textValue((string) $row['titular']);
    $municipio = textValue((string) $row['municipio']);
    $classe = textValue((string) $row['classe']);
    $porte = textValue((string) $row['porte']);
    $tipo = textValue((string) $row['tipo_geracao']);
    $fonte = textValue((string) $row['fonte']);
    $distribuidora = textValue((string) $row['distribuidora']);

    $busca = implode(' ', [$nome, $titular, $municipio, $classe, $porte, $tipo, $fonte, $distribuidora]);

    $update->execute([
        ':id' => (int) $row['id'],
        ':nome' => $nome,
        ':titular' => $titular,
        ':municipio' => $municipio,
        ':municipio_norm' => norm($municipio),
        ':classe' => $classe,
        ':classe_norm' => norm($classe),
        ':porte' => $porte,
        ':porte_norm' => norm($porte),
        ':tipo_geracao' => $tipo,
        ':tipo_norm' => norm($tipo),
        ':fonte' => $fonte,
        ':distribuidora' => $distribuidora,
        ':busca_norm' => norm($busca),
    ]);

    $count++;
    if ($count % 10000 === 0) {
        $pdo->commit();
        echo "Reparados {$count} registros...\n";
        $pdo->beginTransaction();
    }
}

$pdo->commit();
echo "Pronto. {$count} registros reparados.\n";

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

function norm(string $text): string
{
    $text = textValue($text);
    $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($converted === false) {
        $converted = @iconv('Windows-1252', 'ASCII//TRANSLIT//IGNORE', $text);
    }

    $text = $converted !== false ? $converted : preg_replace('/[^\x20-\x7E]/', '', $text);
    $text = strtolower($text);

    return trim((string) preg_replace('/\s+/', ' ', $text));
}
