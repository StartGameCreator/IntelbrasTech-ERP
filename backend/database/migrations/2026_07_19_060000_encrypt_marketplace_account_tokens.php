<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{Crypt, DB};

return new class extends Migration {
    public function up(): void
    {
        DB::table('marketplace_accounts')->orderBy('id')->chunkById(100, function ($contas) {
            foreach ($contas as $conta) {
                $dados = [];
                foreach (['access_token', 'refresh_token'] as $campo) {
                    $valor = $conta->{$campo};
                    if ($valor === null || $valor === '') continue;
                    try {
                        Crypt::decryptString($valor);
                    } catch (\Throwable) {
                        $dados[$campo] = Crypt::encryptString($valor);
                    }
                }
                if ($dados) DB::table('marketplace_accounts')->where('id', $conta->id)->update($dados);
            }
        });
    }

    public function down(): void
    {
        // Credenciais não devem voltar a texto puro em um rollback.
    }
};
