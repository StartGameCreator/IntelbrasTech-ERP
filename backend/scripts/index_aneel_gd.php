<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$csvPath = storage_path('app/aneel/empreendimento-geracao-distribuida.csv');
$dbPath = storage_path('app/aneel/gd.sqlite');
$tmpDbPath = $dbPath . '.tmp';

if (! is_file($csvPath)) {
    fwrite(STDERR, "CSV nao encontrado: {$csvPath}\n");
    exit(1);
}

@unlink($tmpDbPath);
$pdo = new PDO('sqlite:' . $tmpDbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode = OFF');
$pdo->exec('PRAGMA synchronous = OFF');
$pdo->exec('PRAGMA temp_store = MEMORY');
$pdo->exec('PRAGMA cache_size = -200000');

$pdo->exec(<<<SQL
CREATE TABLE gd (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    codigo TEXT,
    nome TEXT,
    titular TEXT,
    documento TEXT,
    cep TEXT,
    uf TEXT,
    municipio TEXT,
    municipio_norm TEXT,
    classe TEXT,
    classe_norm TEXT,
    porte TEXT,
    porte_norm TEXT,
    tipo_geracao TEXT,
    tipo_norm TEXT,
    fonte TEXT,
    potencia_mw REAL,
    latitude REAL,
    longitude REAL,
    data_conexao TEXT,
    distribuidora TEXT,
    busca_norm TEXT
)
SQL);

$insert = $pdo->prepare(<<<SQL
INSERT INTO gd (
    codigo, nome, titular, documento, cep, uf, municipio, municipio_norm, classe, classe_norm,
    porte, porte_norm, tipo_geracao, tipo_norm, fonte, potencia_mw, latitude, longitude,
    data_conexao, distribuidora, busca_norm
) VALUES (
    :codigo, :nome, :titular, :documento, :cep, :uf, :municipio, :municipio_norm, :classe, :classe_norm,
    :porte, :porte_norm, :tipo_geracao, :tipo_norm, :fonte, :potencia_mw, :latitude, :longitude,
    :data_conexao, :distribuidora, :busca_norm
)
SQL);

$stream = fopen($csvPath, 'rb');
if (! $stream) {
    fwrite(STDERR, "Nao foi possivel abrir o CSV.\n");
    exit(1);
}

$header = readCsv($stream);
if (! is_array($header)) {
    fwrite(STDERR, "Cabecalho CSV invalido.\n");
    exit(1);
}

$pdo->beginTransaction();
$count = 0;
$inserted = 0;

while (($row = readCsv($stream)) !== false) {
    if (count($row) !== count($header)) {
        continue;
    }

    $record = array_combine($header, $row);
    if (! is_array($record)) {
        continue;
    }

    $item = normalizeRecord($record);
    $insert->execute($item);
    $inserted++;
    $count++;

    if ($count % 10000 === 0) {
        $pdo->commit();
        echo "Indexados {$count} registros...\n";
        $pdo->beginTransaction();
    }
}

$pdo->commit();
fclose($stream);

$pdo->exec('CREATE INDEX idx_gd_uf ON gd (uf)');
$pdo->exec('CREATE INDEX idx_gd_uf_cidade ON gd (uf, municipio_norm)');
$pdo->exec('CREATE INDEX idx_gd_perfil ON gd (uf, classe_norm, porte_norm, tipo_norm)');
$pdo->exec('CREATE INDEX idx_gd_tipo ON gd (tipo_norm)');

@unlink($dbPath);
rename($tmpDbPath, $dbPath);

echo "Pronto. {$inserted} registros indexados em {$dbPath}\n";

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

function normalizeRecord(array $record): array
{
    $titular = textValue(field($record, ['NomTitularEmpreendimento', 'NomeTitular', 'NomTitular', 'Titular']));
    $municipio = cleanCity(textValue(field($record, ['NomMunicipio', 'DscMuninicpios', 'DscMunicipio', 'Municipio', 'municipio'])));
    $classe = textValue(field($record, ['DscClasseConsumo', 'ClasseConsumo', 'CodClasseConsumo']));
    $porte = textValue(field($record, ['DscPorte', 'Porte', 'DscPorteUsina']));
    $tipo = textValue(field($record, ['SigTipoGeracao', 'TipoGeracao']));
    $fonte = textValue(field($record, ['DscFonteGeracao', 'NomFonteCombustivel', 'Fonte']));
    $distribuidora = textValue(field($record, ['NomAgente', 'SigAgente', 'NomDistribuidora', 'NomeDistribuidora', 'Distribuidora']));
    $codigo = textValue(field($record, ['CodEmpreendimento', 'CodCEG', 'cod_ceg', 'codigo', '_id']));
    $documento = preg_replace('/\D/', '', textValue(field($record, ['NumCPFCNPJ', 'CNPJ', 'CPF'])));
    $cep = preg_replace('/\D/', '', textValue(field($record, ['CodCEP', 'CEP', 'cep'])));

    $busca = implode(' ', [
        $codigo,
        $titular,
        $documento,
        $municipio,
        field($record, ['SigUF', 'SigUFPrincipal', 'UF', 'uf']),
        $classe,
        $porte,
        $tipo,
        $fonte,
        $distribuidora,
    ]);

    return [
        ':codigo' => $codigo,
        ':nome' => $titular ?: 'Microgeracao ANEEL',
        ':titular' => $titular,
        ':documento' => $documento,
        ':cep' => $cep,
        ':uf' => strtoupper(textValue(field($record, ['SigUF', 'SigUFPrincipal', 'UF', 'uf']))),
        ':municipio' => $municipio,
        ':municipio_norm' => norm($municipio),
        ':classe' => $classe,
        ':classe_norm' => norm($classe),
        ':porte' => $porte,
        ':porte_norm' => norm($porte),
        ':tipo_geracao' => $tipo,
        ':tipo_norm' => norm($tipo),
        ':fonte' => $fonte,
        ':potencia_mw' => numberValue(field($record, ['MdaPotenciaInstaladaKW', 'MdaPotenciaInstaladaKw', 'MdaPotenciaInstalada', 'PotenciaInstaladaKW', 'PotInstaladaKW'])) / 1000,
        ':latitude' => numberValue(field($record, ['NumCoordNEmpreendimento', 'NumCoordN', 'Latitude', 'latitude', 'NumLatitude'])),
        ':longitude' => numberValue(field($record, ['NumCoordEEmpreendimento', 'NumCoordE', 'Longitude', 'longitude', 'NumLongitude'])),
        ':data_conexao' => textValue(field($record, ['DthAtualizaCadastralEmpreend', 'DatConexao', 'DatEntradaOperacao', 'DataConexao'])),
        ':distribuidora' => $distribuidora,
        ':busca_norm' => norm($busca),
    ];
}

function field(array $record, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $record) && $record[$key] !== null && $record[$key] !== '') {
            return trim((string) $record[$key]);
        }
    }

    return '';
}

function cleanCity(string $city): string
{
    return trim((string) preg_replace('/\s+-\s+[A-Z]{2}$/', '', trim($city)));
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

function numberValue(string $value): float
{
    $value = str_replace(',', '.', preg_replace('/[^\d,\.\-]/', '', $value));

    return is_numeric($value) ? (float) $value : 0.0;
}
