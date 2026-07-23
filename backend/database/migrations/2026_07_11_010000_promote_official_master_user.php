<?php

use App\Models\{Perfil, Usuario};
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $email = 'elvisvasconcelos@intelbrastech.com.br';
        $usuario = Usuario::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$usuario) {
            return;
        }

        $perfil = Perfil::firstOrCreate(
            ['empresa_id' => $usuario->empresa_id, 'nome' => 'Master'],
            [
                'descricao' => 'Administrador Master oficial do IntelbrasTech ERP',
                'admin' => true,
                'ativo' => true,
            ]
        );

        if (!$perfil->admin || !$perfil->ativo) {
            $perfil->update(['admin' => true, 'ativo' => true]);
        }

        $usuario->update([
            'perfil_id' => $perfil->id,
            'ativo' => true,
            'email_verificado_em' => $usuario->email_verificado_em ?: now(),
        ]);
    }

    public function down(): void
    {
        // Não rebaixamos automaticamente a conta Master em rollback.
    }
};
