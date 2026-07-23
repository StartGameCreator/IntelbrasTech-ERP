<?php
namespace App\Services\Social;
use App\Models\ApiIntegration;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class SocialProviderService
{
    private function providerConfig(string $provider): array {
        $base=config("social.$provider")?:[]; $key='social_'.$provider;
        $query=ApiIntegration::where('provider',$key)->where('enabled',true);
        if(auth()->check()&&auth()->user()->empresa_id){
            $query->where('empresa_id',auth()->user()->empresa_id);
        } else {
            $query->orderByDesc('is_primary')->orderBy('id');
        }
        $item=$query->first(); if(!$item)return $base;
        $credentials=$item->getCredentials();$settings=$item->settings?:[];
        return array_merge($base,[
            'client_id'=>$credentials['client_id']??($base['client_id']??null),
            'client_secret'=>$credentials['client_secret']??($base['client_secret']??null),
            'team_id'=>$credentials['team_id']??($base['team_id']??null),
            'key_id'=>$credentials['key_id']??($base['key_id']??null),
            'private_key'=>$credentials['private_key']??($base['private_key']??null),
            'redirect'=>$settings['redirect_uri']??($base['redirect']??null),
            'tenant'=>$settings['tenant']??($base['tenant']??'common'),
            'sync_calendar'=>(bool)($settings['sync_calendar']??true),
            'sync_business'=>(bool)($settings['sync_business']??true),
        ]);
    }
    public function authorizationUrl(string $provider,string $state,?string $challenge=null): string {
        $c=$this->providerConfig($provider);if(!$c||empty($c['client_id']))throw new RuntimeException("Provedor $provider não configurado.");
        return match($provider){
            'google'=>'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$c['client_id'],'redirect_uri'=>$c['redirect'],'response_type'=>'code','scope'=>implode(' ',$c['scopes']),'state'=>$state,'access_type'=>'offline','prompt'=>'consent','include_granted_scopes'=>'true','code_challenge'=>$challenge,'code_challenge_method'=>'S256']),
            'microsoft'=>"https://login.microsoftonline.com/{$c['tenant']}/oauth2/v2.0/authorize?".http_build_query(['client_id'=>$c['client_id'],'redirect_uri'=>$c['redirect'],'response_type'=>'code','scope'=>implode(' ',$c['scopes']),'state'=>$state,'response_mode'=>'query','code_challenge'=>$challenge,'code_challenge_method'=>'S256']),
            'facebook'=>'https://www.facebook.com/v23.0/dialog/oauth?'.http_build_query(['client_id'=>$c['client_id'],'redirect_uri'=>$c['redirect'],'response_type'=>'code','scope'=>implode(',',$c['scopes']),'state'=>$state]),
            'apple'=>'https://appleid.apple.com/auth/authorize?'.http_build_query(['client_id'=>$c['client_id'],'redirect_uri'=>$c['redirect'],'response_type'=>'code id_token','response_mode'=>'form_post','scope'=>implode(' ',$c['scopes']),'state'=>$state]),default=>throw new RuntimeException('Provedor inválido.')};
    }
    public function exchange(string $provider,string $code,?string $verifier=null): array {
        $c=$this->providerConfig($provider);$payload=match($provider){'google'=>['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$c['redirect'],'code_verifier'=>$verifier],'microsoft'=>['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$c['redirect'],'scope'=>implode(' ',$c['scopes']),'code_verifier'=>$verifier],'facebook'=>['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'code'=>$code,'redirect_uri'=>$c['redirect']],'apple'=>['client_id'=>$c['client_id'],'client_secret'=>$this->appleClientSecret($c),'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$c['redirect']]};
        $url=match($provider){'google'=>'https://oauth2.googleapis.com/token','microsoft'=>"https://login.microsoftonline.com/{$c['tenant']}/oauth2/v2.0/token",'facebook'=>'https://graph.facebook.com/v23.0/oauth/access_token','apple'=>'https://appleid.apple.com/auth/token'};
        return Http::asForm()->timeout(25)->post($url,array_filter($payload,fn($v)=>$v!==null))->throw()->json();
    }
    public function profile(string $provider,array $tokens,array $callback=[]): array{return match($provider){'google'=>$this->googleProfile($tokens['access_token']),'microsoft'=>$this->microsoftProfile($tokens['access_token']),'facebook'=>$this->facebookProfile($tokens['access_token']),'apple'=>$this->appleProfile($tokens['id_token']??$callback['id_token']??'',$callback['user']??null)};}
    private function googleProfile(string $t):array{$p=Http::withToken($t)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();return['id'=>$p['sub'],'email'=>$p['email']??null,'name'=>$p['name']??($p['email']??'Google'),'avatar'=>$p['picture']??null,'raw'=>$p];}
    private function microsoftProfile(string $t):array{$p=Http::withToken($t)->get('https://graph.microsoft.com/v1.0/me?$select=id,displayName,mail,userPrincipalName')->throw()->json();return['id'=>$p['id'],'email'=>$p['mail']??$p['userPrincipalName']??null,'name'=>$p['displayName']??'Microsoft','avatar'=>null,'raw'=>$p];}
    private function facebookProfile(string $t):array{$p=Http::get('https://graph.facebook.com/v23.0/me',['fields'=>'id,name,email,picture.type(large)','access_token'=>$t])->throw()->json();return['id'=>$p['id'],'email'=>$p['email']??null,'name'=>$p['name']??'Facebook','avatar'=>data_get($p,'picture.data.url'),'raw'=>$p];}
    private function appleProfile(string $idToken,mixed $userJson):array{if(!$idToken)throw new RuntimeException('Apple não retornou id_token.');$parts=explode('.',$idToken);$payload=json_decode(base64_decode(strtr($parts[1],'-_','+/')),true)?:[];$user=is_string($userJson)?json_decode($userJson,true):(array)$userJson;$name=trim(($user['name']['firstName']??'').' '.($user['name']['lastName']??''));return['id'=>$payload['sub']??null,'email'=>$payload['email']??($user['email']??null),'name'=>$name?:($payload['email']??'Apple'),'avatar'=>null,'raw'=>$payload];}
    private function appleClientSecret(array $c):string{$key=$c['private_key']??null;if(!$key&&($c['private_key_path']??null)&&is_file($c['private_key_path']))$key=file_get_contents($c['private_key_path']);if(!$key)throw new RuntimeException('Chave privada Apple não configurada.');return JWT::encode(['iss'=>$c['team_id'],'iat'=>time(),'exp'=>time()+15777000,'aud'=>'https://appleid.apple.com','sub'=>$c['client_id']],$key,'ES256',$c['key_id']);}
}
