<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA busy_timeout = 30000');
        DB::statement('PRAGMA foreign_keys = ON');
        DB::statement('PRAGMA journal_mode = WAL');
        DB::statement('PRAGMA synchronous = NORMAL');
        DB::statement('PRAGMA wal_autocheckpoint = 1000');
    }

    public function down(): void
    {
        // Não desativa WAL automaticamente para evitar regressão de concorrência.
    }
};
