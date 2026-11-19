<?php
namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\{MarketplaceAccount,MarketplaceConnector};
use App\Services\Marketplace\MercadoLivreOAuthService;
use Illuminate\Http\{JsonResponse,Request};
use Illuminate\Support\Str;
use Throwable;

class MercadoLivreOAuthController extends Controller
{
    public function __construct(private readonly MercadoLivreOAuthService $oauth) {}

    public function status(): JsonResponse {
        $a=$this->account(false);
        return response()->json([
            'configured'=>(bool)(config('marketplaces.mercado_livre.client_id') && config('marketplaces.mercado_livre.client_secret')),
            'redirect_uri'=>config('marketplaces.mercado_livre.redirect_uri'),
            'account'=>$a ? [
                'status'=>$a->status,'nome_conta'=>$a->nome_conta,'nickname'=>$a->nickname,
                'external_user_id'=>$a->external_user_id,'site_id'=>$a->site_id,
                'token_expires_at'=>$a->token_expires_at,'erro_conexao'=>$a->erro_conexao,
            ] : null,
        ]);
    }

    public function connect(): JsonResponse {
        $connector=MarketplaceConnector::where('slug','mercado_livre')->firstOrFail();
        $state=Str::random(64);
        $a=MarketplaceAccount::firstOrCreate(
            ['empresa_id'=>auth()->user()->empresa_id,'connector_id'=>$connector->id],
            ['nome_conta'=>'Mercado Livre','status'=>'desconectado']
        );
        $a->update(['oauth_state'=>hash('sha256',$state),'oauth_state_expires_at'=>now()->addMinutes(15),'erro_conexao'=>null]);
        return response()->json(['authorization_url'=>$this->oauth->authorizationUrl($state)]);
    }

    public function callback(Request $request) {
        $frontend=rtrim(config('marketplaces.frontend_url'),'/');
        if($request->filled('error')) return redirect($frontend.'/marketplaces/mercado-livre?error='.urlencode((string)$request->get('error_description',$request->get('error'))));
        $data=$request->validate(['code'=>'required|string','state'=>'required|string']);
        $connector=MarketplaceConnector::where('slug','mercado_livre')->firstOrFail();
        $a=MarketplaceAccount::where('connector_id',$connector->id)
            ->where('oauth_state',hash('sha256',$data['state']))
            ->where('oauth_state_expires_at','>',now())->first();
        if(!$a) return redirect($frontend.'/marketplaces/mercado-livre?error='.urlencode('State OAuth inválido ou expirado.'));
        try {
            $t=$this->oauth->exchange($data['code']);
            $p=$this->oauth->me($t['access_token']);
            $a->update([
                'external_user_id'=>(string)($p['id']??$t['user_id']??''),
                'nickname'=>$p['nickname']??null,'site_id'=>$p['site_id']??null,
                'nome_conta'=>$p['nickname']??'Mercado Livre','status'=>'conectado',
                'access_token'=>encrypt($t['access_token']),
                'refresh_token'=>isset($t['refresh_token'])?encrypt($t['refresh_token']):null,
                'token_expires_at'=>now()->addSeconds((int)($t['expires_in']??21600)),
                'oauth_state'=>null,'oauth_state_expires_at'=>null,'erro_conexao'=>null,
                'ultima_sincronizacao_em'=>now(),
            ]);
            return redirect($frontend.'/marketplaces/mercado-livre?connected=1');
        } catch(Throwable $e) {
            $a->update(['status'=>'erro','erro_conexao'=>$e->getMessage(),'oauth_state'=>null,'oauth_state_expires_at'=>null]);
            return redirect($frontend.'/marketplaces/mercado-livre?error='.urlencode($e->getMessage()));
        }
    }

    public function test(): JsonResponse {
        $a=$this->account(true);
        try {
            $p=$this->oauth->me(decrypt($a->access_token));
            $a->update(['status'=>'conectado','nickname'=>$p['nickname']??$a->nickname,'site_id'=>$p['site_id']??$a->site_id,'erro_conexao'=>null,'ultima_sincronizacao_em'=>now()]);
            return response()->json(['message'=>'Conexão validada com sucesso.','profile'=>$p]);
        } catch(Throwable $e) { return response()->json(['message'=>$e->getMessage()],422); }
    }

    public function refreshToken(): JsonResponse {
        $a=$this->account(true);
        if(!$a->refresh_token) return response()->json(['message'=>'Refresh token não disponível.'],422);
        try {
            $t=$this->oauth->refresh(decrypt($a->refresh_token));
            $a->update([
                'access_token'=>encrypt($t['access_token']),
                'refresh_token'=>isset($t['refresh_token'])?encrypt($t['refresh_token']):$a->refresh_token,
                'token_expires_at'=>now()->addSeconds((int)($t['expires_in']??21600)),
                'status'=>'conectado','erro_conexao'=>null,
            ]);
            return response()->json(['message'=>'Token renovado com sucesso.']);
        } catch(Throwable $e) { return response()->json(['message'=>$e->getMessage()],422); }
    }

    public function disconnect(): JsonResponse {
        $a=$this->account(false);
        if($a) $a->update(['status'=>'desconectado','access_token'=>null,'refresh_token'=>null,'token_expires_at'=>null,'erro_conexao'=>null]);
        return response()->json(['message'=>'Conta desconectada.']);
    }

    private function account(bool $required): ?MarketplaceAccount {
        $c=MarketplaceConnector::where('slug','mercado_livre')->firstOrFail();
        $q=MarketplaceAccount::where('empresa_id',auth()->user()->empresa_id)->where('connector_id',$c->id);
        return $required ? $q->whereNotNull('access_token')->firstOrFail() : $q->first();
    }
}
