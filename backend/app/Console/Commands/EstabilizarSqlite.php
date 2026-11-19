<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EstabilizarSqlite extends Command
{
    protected $signature = 'sistema:estabilizar-sqlite';
    protected $description = 'Aplica WAL, timeout e checkpoint no SQLite do IntelbrasTech ERP.';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->info('Banco atual não é SQLite. Nenhuma alteração necessária.');
            return self::SUCCESS;
        }

        DB::statement('PRAGMA busy_timeout = 30000');
        DB::statement('PRAGMA foreign_keys = ON');
        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA synchronous = NORMAL');
        DB::statement('PRAGMA wal_autocheckpoint = 1000');
        DB::statement('PRAGMA optimize');
        DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');

        $this->info('SQLite estabilizado: WAL, timeout e checkpoint aplicados.');
        return self::SUCCESS;
    }
}
