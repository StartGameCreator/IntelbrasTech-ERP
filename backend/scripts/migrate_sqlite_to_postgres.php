<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sourcePath = $argv[1] ?? null;
if (!$sourcePath || !is_file($sourcePath)) {
    fwrite(STDERR, "Uso: php scripts/migrate_sqlite_to_postgres.php /caminho/database.sqlite\n");
    exit(1);
}
if (DB::getDriverName() !== 'pgsql') {
    fwrite(STDERR, "A conexão de destino deve ser PostgreSQL.\n");
    exit(1);
}

$source = new PDO('sqlite:'.$sourcePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
/** @var ConnectionInterface $target */
$target = DB::connection();

$excludedTables = [
    'migrations', 'cache', 'cache_locks', 'sessions', 'jobs',
    'job_batches', 'failed_jobs', 'password_reset_tokens',
];
$sourceTables = $source->query(
    "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY rowid"
)->fetchAll(PDO::FETCH_COLUMN);
$targetTables = $target->select(
    "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
);
$targetTableNames = array_map(static fn ($row) => $row->table_name, $targetTables);
$tables = array_values(array_filter(
    $sourceTables,
    static fn (string $table) => in_array($table, $targetTableNames, true)
        && !in_array($table, $excludedTables, true)
));
if ($tables === []) {
    fwrite(STDERR, "Nenhuma tabela comum encontrada entre origem e destino.\n");
    exit(1);
}

function quoteIdentifier(string $identifier): string
{
    return '"'.str_replace('"', '""', $identifier).'"';
}

function normalizeValue(mixed $value, string $type): mixed
{
    if ($value === null) {
        return null;
    }
    if ($type === 'boolean') {
        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? ((int) $value !== 0);
    }
    if (in_array($type, ['smallint', 'integer', 'bigint'], true)) {
        return $value === '' ? null : (int) $value;
    }
    if (in_array($type, ['numeric', 'decimal', 'real', 'double precision'], true)) {
        return $value === '' ? null : $value;
    }
    if (in_array($type, ['date', 'timestamp without time zone', 'timestamp with time zone'], true)) {
        return $value === '' || str_starts_with((string) $value, '0000-00-00') ? null : $value;
    }
    if (in_array($type, ['json', 'jsonb'], true) && $value !== '') {
        json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }
    return is_resource($value) ? stream_get_contents($value) : $value;
}

$stats = [];
$quotedTables = implode(', ', array_map('quoteIdentifier', $tables));
$target->beginTransaction();

try {
    $target->statement('SET LOCAL session_replication_role = replica');
    $target->statement("TRUNCATE TABLE {$quotedTables} RESTART IDENTITY CASCADE");

    foreach ($tables as $table) {
        $sourceColumns = $source->query('PRAGMA table_info('.quoteIdentifier($table).')')->fetchAll();
        $sourceColumnNames = array_column($sourceColumns, 'name');
        $targetColumns = $target->select(
            "SELECT column_name, data_type FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ? AND is_generated = 'NEVER'
             ORDER BY ordinal_position",
            [$table]
        );
        $types = [];
        foreach ($targetColumns as $column) {
            if (in_array($column->column_name, $sourceColumnNames, true)) {
                $types[$column->column_name] = $column->data_type;
            }
        }
        if ($types === []) {
            continue;
        }

        $columns = array_keys($types);
        $select = 'SELECT '.implode(', ', array_map('quoteIdentifier', $columns))
            .' FROM '.quoteIdentifier($table);
        $statement = $source->query($select);
        $batch = [];
        $sourceCount = 0;
        while ($row = $statement->fetch()) {
            $normalized = [];
            foreach ($columns as $column) {
                $normalized[$column] = normalizeValue($row[$column], $types[$column]);
            }
            $batch[] = $normalized;
            $sourceCount++;
            if (count($batch) >= 200) {
                $target->table($table)->insert($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $target->table($table)->insert($batch);
        }

        $targetCount = (int) $target->table($table)->count();
        if ($targetCount !== $sourceCount) {
            throw new RuntimeException("Contagem divergente em {$table}: SQLite={$sourceCount}, PostgreSQL={$targetCount}");
        }
        $stats[$table] = $targetCount;
        printf("%-45s %d\n", $table, $targetCount);
    }

    foreach ($tables as $table) {
        if (!$target->getSchemaBuilder()->hasColumn($table, 'id')) {
            continue;
        }
        $sequence = $target->selectOne('SELECT pg_get_serial_sequence(?, ?) AS name', [$table, 'id']);
        if (!empty($sequence?->name)) {
            $target->statement(
                'SELECT setval(?, COALESCE(MAX(id), 1), MAX(id) IS NOT NULL) FROM '.quoteIdentifier($table),
                [$sequence->name]
            );
        }
    }
    $target->commit();
} catch (Throwable $error) {
    $target->rollBack();
    fwrite(STDERR, "Migração revertida: {$error->getMessage()}\n");
    exit(1);
}

printf("Migração concluída: %d tabelas, %d registros.\n", count($stats), array_sum($stats));
