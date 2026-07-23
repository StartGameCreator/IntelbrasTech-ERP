<?php

namespace App\Services\MarketplaceTecnicos;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplaceAccountService
{
    public function cadastrarTradicional(string $perfil, array $dados): Usuario
    {
        return DB::transaction(function () use ($perfil, $dados) {
            $usuario = $this->criarOuLocalizarUsuario(
                perfil: $perfil,
                nome: $dados['nome'],
                email: $dados['email'],
                senha: $dados['senha'],
                avatar: null
            );

            $this->vincularPerfilMarketplace($perfil, $usuario, $dados);

            return $usuario->load('empresa', 'perfil.permissoes');
        });
    }

    public function cadastrarSocial(array $profile, array $context): Usuario
    {
        return DB::transaction(function () use ($profile, $context) {
            $perfil = $context['perfil'] ?? null;
            $dados = $context['dados'] ?? [];

            if (!in_array($perfil, ['tecnico', 'cliente'], true)) {
                throw new RuntimeException('Perfil de cadastro social inválido.');
            }

            $nome = trim((string) ($dados['nome'] ?? $profile['name'] ?? ''));
            $email = mb_strtolower(trim((string) ($profile['email'] ?? '')));

            if ($nome === '' || $email === '') {
                throw new RuntimeException('O provedor social não forneceu nome e e-mail válidos.');
            }

            $dados['nome'] = $nome;
            $dados['email'] = $email;

            $usuario = $this->criarOuLocalizarUsuario(
                perfil: $perfil,
                nome: $nome,
                email: $email,
                senha: Str::random(64),
                avatar: $profile['avatar'] ?? null
            );

            $this->vincularPerfilMarketplace($perfil, $usuario, $dados);

            return $usuario->load('empresa', 'perfil.permissoes');
        });
    }

    private function criarOuLocalizarUsuario(
        string $perfil,
        string $nome,
        string $email,
        string $senha,
        ?string $avatar
    ): Usuario {
        $email = mb_strtolower(trim($email));
        $usuario = Usuario::with('empresa', 'perfil.permissoes')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($usuario) {
            return $usuario;
        }

        $empresa = $this->empresaMarketplace();
        $perfilModel = $this->perfilMarketplace($empresa, $perfil);

        return Usuario::create([
            'empresa_id' => $empresa->id,
            'perfil_id' => $perfilModel->id,
            'nome' => $nome,
            'email' => $email,
            'senha' => Hash::make($senha),
            'avatar' => $avatar,
            'cargo' => $perfil === 'tecnico' ? 'Técnico Marketplace' : 'Cliente Marketplace',
            'ativo' => $perfil === 'cliente',
            'tema' => 'dark',
            'preferencias' => [
                'marketplace_perfil' => $perfil,
                'cadastro_origem' => $avatar ? 'social' : 'tradicional',
            ],
        ]);
    }

    private function empresaMarketplace(): Empresa
    {
        return Empresa::firstOrCreate(
            ['cnpj' => '00000000000191'],
            [
                'razao_social' => 'IntelbrasTech Marketplace Nacional',
                'nome_fantasia' => 'IntelbrasTech Marketplace',
                'email' => 'marketplace@intelbrastech.com.br',
                'ativo' => true,
                'plano' => 'mvp',
                'cadastro_pendente' => false,
                'dados_fonte' => 'Sistema',
            ]
        );
    }

    private function perfilMarketplace(Empresa $empresa, string $perfil): Perfil
    {
        $nome = $perfil === 'tecnico' ? 'Técnico Marketplace' : 'Cliente Marketplace';

        return Perfil::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nome' => $nome],
            [
                'descricao' => $nome . ' do Marketplace Nacional IntelbrasTech',
                'admin' => false,
                'ativo' => true,
            ]
        );
    }

    private function vincularPerfilMarketplace(string $perfil, Usuario $usuario, array $dados): void
    {
        if ($perfil === 'tecnico') {
            $this->vincularTecnico($usuario, $dados);
            return;
        }

        $this->vincularCliente($usuario, $dados);
    }

    private function vincularTecnico(Usuario $usuario, array $dados): void
    {
        $categorias = $dados['categorias'] ?? [];
        unset($dados['categorias'], $dados['senha'], $dados['senha_confirmation']);

        foreach (['cpf', 'cnpj', 'cep'] as $field) {
            if (array_key_exists($field, $dados) && $dados[$field] !== null) {
                $dados[$field] = preg_replace('/\D+/', '', (string) $dados[$field]);
            }
        }

        $existing = DB::table('marketplace_tecnicos')
            ->where('usuario_id', $usuario->id)
            ->orWhere('email', $usuario->email)
            ->first();

        if ($existing) {
            $id = $existing->id;
            DB::table('marketplace_tecnicos')->where('id', $id)->update(array_merge($dados, [
                'usuario_id' => $usuario->id,
                'email' => $usuario->email,
                'status' => 'pendente',
                'updated_at' => now(),
            ]));
        } else {
            $id = DB::table('marketplace_tecnicos')->insertGetId(array_merge($dados, [
                'uuid' => (string) Str::uuid(),
                'usuario_id' => $usuario->id,
                'empresa_id' => $usuario->empresa_id,
                'email' => $usuario->email,
                'status' => 'pendente',
                'online' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        DB::table('marketplace_tecnico_categorias')->where('tecnico_id', $id)->delete();

        foreach (array_values(array_unique($categorias)) as $index => $categoriaId) {
            DB::table('marketplace_tecnico_categorias')->insert([
                'tecnico_id' => $id,
                'categoria_id' => $categoriaId,
                'principal' => $index === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function vincularCliente(Usuario $usuario, array $dados): void
    {
        unset($dados['senha'], $dados['senha_confirmation'], $dados['categorias']);

        foreach (['cpf', 'cnpj', 'cep'] as $field) {
            if (array_key_exists($field, $dados) && $dados[$field] !== null) {
                $dados[$field] = preg_replace('/\D+/', '', (string) $dados[$field]);
            }
        }

        $existing = DB::table('marketplace_clientes')
            ->where('usuario_id', $usuario->id)
            ->orWhere('email', $usuario->email)
            ->first();

        $payload = array_merge($dados, [
            'usuario_id' => $usuario->id,
            'empresa_id' => $usuario->empresa_id,
            'email' => $usuario->email,
            'status' => 'aprovado',
            'aprovado_em' => now(),
            'updated_at' => now(),
        ]);

        if ($existing) {
            DB::table('marketplace_clientes')->where('id', $existing->id)->update($payload);
        } else {
            DB::table('marketplace_clientes')->insert(array_merge($payload, [
                'uuid' => (string) Str::uuid(),
                'created_at' => now(),
            ]));
        }
    }
}
