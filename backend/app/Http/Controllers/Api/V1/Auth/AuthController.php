<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Hash, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->email))]);
        $request->validate([
            'email' => 'required|email',
            'senha' => 'required|string',
        ]);

        // Rate limiting: 5 tentativas por IP a cada 60s
        $key = 'login.' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ], 429);
        }

        $usuario = Usuario::with('perfil.permissoes', 'empresa')
            ->where('email', $request->email)
            ->first();

        if (! $usuario || ! Hash::check($request->senha, $usuario->senha)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        if (! $usuario->ativo) {
            return response()->json(['message' => 'Usuário inativo. Entre em contato com o administrador.'], 403);
        }

        if (! $usuario->empresa->emTrialOuAtiva()) {
            return response()->json(['message' => 'Plano expirado. Renove sua assinatura.'], 402);
        }

        RateLimiter::clear($key);
        $usuario->registrarAcesso();

        $token = $usuario->createToken('api-token')->plainTextToken;

        return response()->json([
            'token'   => $token,
            'usuario' => $this->formatarUsuario($usuario),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request): JsonResponse
    {
        $usuario = $request->user()->load('perfil.permissoes', 'empresa');
        return response()->json($this->formatarUsuario($usuario));
    }

    public function atualizarPerfil(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $dados = $request->validate([
            'nome'      => 'sometimes|string|max:150',
            'telefone'  => 'sometimes|nullable|string|max:20',
            'cargo'     => 'sometimes|nullable|string|max:100',
            'tema'      => 'sometimes|in:dark,light,system',
            'preferencias' => 'sometimes|array',
        ]);

        $usuario->update($dados);

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'usuario' => $this->formatarUsuario($usuario->fresh('perfil', 'empresa')),
        ]);
    }

    public function alterarSenha(Request $request): JsonResponse
    {
        $request->validate([
            'senha_atual' => 'required|string',
            'senha_nova'  => 'required|string|min:8|confirmed',
        ]);

        $usuario = $request->user();

        if (! Hash::check($request->senha_atual, $usuario->senha)) {
            throw ValidationException::withMessages([
                'senha_atual' => ['Senha atual incorreta.'],
            ]);
        }

        $usuario->update(['senha' => Hash::make($request->senha_nova)]);
        $usuario->tokens()->delete(); // Invalida todos os tokens

        $token = $usuario->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Senha alterada com sucesso.',
            'token'   => $token,
        ]);
    }

    public function esqueciSenha(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->email))]);
        $request->validate(['email' => 'required|email']);

        $usuario = Usuario::where('email', $request->email)->first();

        // Não revela se o e-mail existe ou não (evita enumeração de usuários).
        if (! $usuario) {
            return response()->json(['message' => 'Se o e-mail existir em nossa base, um link de redefinição será enviado.']);
        }

        $token = \Illuminate\Support\Str::random(64);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $usuario->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // Envio de e-mail fica como próximo passo (Notification/Mailable dedicado);
        // por ora o token é retornado apenas em ambiente local para testes manuais.
        return response()->json([
            'message' => 'Se o e-mail existir em nossa base, um link de redefinição será enviado.',
            'debug_token' => app()->environment('local') ? $token : null,
        ]);
    }

    public function redefinirSenha(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->email))]);
        $dados = $request->validate([
            'email'        => 'required|email',
            'token'        => 'required|string',
            'senha_nova'   => 'required|string|min:8|confirmed',
        ]);

        $registro = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $dados['email'])
            ->first();

        if (! $registro || ! Hash::check($dados['token'], $registro->token)) {
            throw ValidationException::withMessages(['token' => ['Token inválido ou expirado.']]);
        }

        if (now()->diffInMinutes($registro->created_at) > 60) {
            throw ValidationException::withMessages(['token' => ['Token expirado. Solicite um novo.']]);
        }

        $usuario = Usuario::where('email', $dados['email'])->firstOrFail();
        $usuario->update(['senha' => Hash::make($dados['senha_nova'])]);
        $usuario->tokens()->delete();

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $dados['email'])->delete();

        return response()->json(['message' => 'Senha redefinida com sucesso. Faça login novamente.']);
    }

    private function formatarUsuario(Usuario $usuario): array
    {
        $perfilOperacional = DB::table('platform_perfis_usuario')
            ->where('usuario_id', $usuario->id)
            ->where('status', 'ativo')
            ->orderByRaw("case when tipo_perfil = 'tecnico' then 0 else 1 end")
            ->value('tipo_perfil');

        return [
            'id'          => $usuario->id,
            'nome'        => $usuario->nome,
            'email'       => $usuario->email,
            'avatar'      => $usuario->avatar,
            'cargo'       => $usuario->cargo,
            'tema'        => $usuario->tema,
            'preferencias'=> $usuario->preferencias,
            'admin'       => $usuario->perfil?->admin ?? false,
            'permissoes'  => $usuario->perfil?->permissoes
                ->map(fn($p) => "{$p->modulo}.{$p->acao}")
                ->toArray() ?? [],
            'perfil_operacional' => $perfilOperacional,
            'empresa' => [
                'id'           => $usuario->empresa->id,
                'razao_social' => $usuario->empresa->razao_social,
                'nome_fantasia'=> $usuario->empresa->nome_fantasia,
                'cnpj'         => $usuario->empresa->cnpj,
                'logo'         => $usuario->empresa->logo,
                'plano'        => $usuario->empresa->plano,
            ],
        ];
    }
}
