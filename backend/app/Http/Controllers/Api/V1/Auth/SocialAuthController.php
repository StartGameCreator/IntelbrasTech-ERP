<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\{Empresa, Perfil, SocialAccount, Usuario};
use App\Services\Social\{GoogleWorkspaceSyncService, SocialProviderService};
use App\Services\MarketplaceTecnicos\MarketplaceAccountService;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Cache, DB, Hash};
use Illuminate\Support\Str;
use Throwable;

class SocialAuthController extends Controller
{
    private const MASTER_EMAIL = 'elvisvasconcelos@intelbrastech.com.br';
    private const PROVIDERS = ['google','microsoft','apple','facebook'];

    public function redirect(string $provider, SocialProviderService $service): JsonResponse
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
        $state = Str::random(64); $verifier = Str::random(96);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        Cache::put("social_state:$state", ['provider'=>$provider,'verifier'=>$verifier], now()->addMinutes(10));
        try { return response()->json(['url'=>$service->authorizationUrl($provider, $state, $challenge)]); }
        catch (Throwable $e) { return response()->json(['message'=>$e->getMessage()], 422); }
    }

    public function callback(Request $request, string $provider, SocialProviderService $service, GoogleWorkspaceSyncService $googleSync, MarketplaceAccountService $marketplaceAccounts): RedirectResponse
    {
        $frontend = rtrim(config('social.frontend_url'), '/');
        try {
            abort_unless(in_array($provider, self::PROVIDERS, true), 404);
            if ($request->filled('error')) throw new \RuntimeException($request->string('error_description')->toString() ?: $request->string('error')->toString());
            $state = $request->string('state')->toString();
            $saved = Cache::pull("social_state:$state");
            if (!$saved || $saved['provider'] !== $provider) throw new \RuntimeException('Estado OAuth inválido ou expirado.');
            $tokens = $service->exchange($provider, $request->string('code')->toString(), $saved['verifier'] ?? null);
            $profile = $service->profile($provider, $tokens, $request->all());
            if (empty($profile['email'])) throw new \RuntimeException('O provedor não forneceu um e-mail utilizável.');
            $email = mb_strtolower(trim((string) $profile['email']));
            $usuario = Usuario::with('empresa','perfil.permissoes')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
            if (!$usuario) {
                $profile['email'] = $email;

                if (!empty($saved['marketplace'])) {
                    $usuario = $marketplaceAccounts->cadastrarSocial($profile, $saved['marketplace']);
                } else {
                    if (!config('social.auto_register')) {
                        throw new \RuntimeException('Não existe usuário cadastrado com este e-mail. Cadastre-o primeiro ou ative SOCIAL_LOGIN_AUTO_REGISTER.');
                    }

                    $usuario = $this->register($profile);
                }
            } elseif (!empty($saved['marketplace'])) {
                $usuario = $marketplaceAccounts->cadastrarSocial($profile, $saved['marketplace']);
            }
            if (!$usuario->ativo) throw new \RuntimeException('Usuário inativo.');
            $this->promoteMaster($usuario);
            $account = SocialAccount::updateOrCreate(
                ['usuario_id'=>$usuario->id,'provider'=>$provider],
                ['empresa_id'=>$usuario->empresa_id,'provider_user_id'=>$profile['id'],'email'=>$profile['email'],
                 'access_token'=>$tokens['access_token'] ?? null,'refresh_token'=>$tokens['refresh_token'] ?? null,
                 'expires_at'=>isset($tokens['expires_in']) ? now()->addSeconds((int)$tokens['expires_in']) : null,
                 'scopes'=>preg_split('/[ ,]+/', $tokens['scope'] ?? '') ?: [],'metadata'=>['profile'=>$profile['raw'] ?? []],'ativo'=>true]
            );
            $usuario->update(array_filter(['avatar'=>$profile['avatar'] ?? null], fn($v)=>$v));
            if ($provider === 'google') $googleSync->sync($account);
            $usuario->registrarAcesso();
            $code = Str::random(80);
            Cache::put("social_login_code:$code", $usuario->id, now()->addMinutes(2));
            return redirect("$frontend/auth/social/callback?code=".urlencode($code));
        } catch (Throwable $e) {
            report($e);
            return redirect("$frontend/auth/social/callback?error=".urlencode($e->getMessage()));
        }
    }

    public function exchange(Request $request): JsonResponse
    {
        $request->validate(['code'=>'required|string']);
        $userId = Cache::pull('social_login_code:'.$request->string('code'));
        if (!$userId) return response()->json(['message'=>'Código social inválido ou expirado.'], 422);
        $u = Usuario::with('empresa','perfil.permissoes')->findOrFail($userId);
        return response()->json(['token'=>$u->createToken('social-login')->plainTextToken,'usuario'=>$this->formatUser($u)]);
    }

    public function connections(Request $request): JsonResponse
    {
        $items = SocialAccount::where('usuario_id',$request->user()->id)->get()->map(fn($a)=>[
            'provider'=>$a->provider,'email'=>$a->email,'ativo'=>$a->ativo,'expires_at'=>$a->expires_at,
            'metadata'=>$a->metadata,'connected_at'=>$a->created_at,
        ]);
        return response()->json($items);
    }

    public function syncGoogle(Request $request, GoogleWorkspaceSyncService $sync): JsonResponse
    {
        $a = SocialAccount::where('usuario_id',$request->user()->id)->where('provider','google')->firstOrFail();
        return response()->json($sync->sync($a));
    }

    public function selectGoogleLocation(Request $request, GoogleWorkspaceSyncService $sync): JsonResponse
    {
        $data = $request->validate(['location'=>'required|array']);
        $a = SocialAccount::where('usuario_id',$request->user()->id)->where('provider','google')->firstOrFail();
        $sync->applyLocation($a, $data['location']);
        return response()->json(['message'=>'Dados da empresa atualizados com o Perfil da Empresa Google.']);
    }

    public function disconnect(Request $request, string $provider): JsonResponse
    {
        SocialAccount::where('usuario_id',$request->user()->id)->where('provider',$provider)->delete();
        return response()->json(['message'=>'Integração desconectada.']);
    }

    private function promoteMaster(Usuario $usuario): void
    {
        if (mb_strtolower((string) $usuario->email) !== self::MASTER_EMAIL) {
            return;
        }

        $perfil = $usuario->perfil;

        if (!$perfil || !$perfil->admin || $perfil->nome !== 'Master') {
            $perfil = Perfil::firstOrCreate(
                [
                    'empresa_id' => $usuario->empresa_id,
                    'nome' => 'Master',
                ],
                [
                    'descricao' => 'Administrador Master do IntelbrasTech ERP',
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
            ]);
            $usuario->setRelation('perfil', $perfil->load('permissoes'));
        }
    }

            private function register(array $p): Usuario
    {
        return DB::transaction(function () use ($p) {
            $masterEmail = 'elvisvasconcelos@intelbrastech.com.br';
            $isMaster = strtolower((string) $p['email']) === $masterEmail;

            /*
             * O ERP continua multiempresa e exige empresa_id no usuário.
             * Porém, o primeiro login cria somente um espaço de trabalho
             * provisório, sem inventar CNPJ.
             *
             * O cadastro jurídico é concluído depois em:
             * Configurações > Dados da empresa.
             */
            $empresa = Empresa::create([
                'razao_social' => $p['name'] ?: 'Empresa em configuração',
                'nome_fantasia' => $p['name'] ?: 'Empresa em configuração',
                'cnpj' => null,
                'email' => $p['email'],
                'ativo' => true,
                'plano' => 'trial',
                'trial_ate' => now()->addDays(30),
                'cadastro_pendente' => true,
                'dados_fonte' => 'Login social',
            ]);

            $perfil = Perfil::create([
                'empresa_id' => $empresa->id,
                'nome' => $isMaster ? 'Master' : 'Administrador',
                'descricao' => $isMaster
                    ? 'Administrador Master oficial do IntelbrasTech ERP'
                    : 'Administrador criado por login social',
                'admin' => true,
                'ativo' => true,
            ]);

            return Usuario::create([
                'empresa_id' => $empresa->id,
                'perfil_id' => $perfil->id,
                'nome' => $p['name'],
                'email' => strtolower((string) $p['email']),
                'senha' => Hash::make(Str::random(64)),
                'avatar' => $p['avatar'] ?? null,
                'ativo' => true,
                'tema' => 'dark',
                'cargo' => $isMaster ? 'Administrador Master' : null,
            ]);
        })->load('empresa', 'perfil.permissoes');
    }

    private function formatUser(Usuario $u): array
    {
        return ['id'=>$u->id,'nome'=>$u->nome,'email'=>$u->email,'avatar'=>$u->avatar,'cargo'=>$u->cargo,'tema'=>$u->tema,
            'preferencias'=>$u->preferencias,'admin'=>$u->perfil?->admin ?? false,
            'permissoes'=>$u->perfil?->permissoes->map(fn($p)=>"{$p->modulo}.{$p->acao}")->toArray() ?? [],
            'empresa'=>['id'=>$u->empresa->id,'razao_social'=>$u->empresa->razao_social,'nome_fantasia'=>$u->empresa->nome_fantasia,'cnpj'=>$u->empresa->cnpj,'logo'=>$u->empresa->logo,'plano'=>$u->empresa->plano,'cadastro_pendente'=>(bool)$u->empresa->cadastro_pendente]];
    }
}
