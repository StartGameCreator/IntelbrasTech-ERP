<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleWorkspaceSyncService
{
    public function sync(SocialAccount $account): array
    {
        $token = $account->access_token;
        $result = ['calendar'=>null,'business_accounts'=>[],'business_locations'=>[],'company_updated'=>false,'warnings'=>[]];
        try {
            $result['calendar'] = Http::withToken($token)->get('https://www.googleapis.com/calendar/v3/users/me/calendarList/primary')->throw()->json();
        } catch (Throwable $e) { $result['warnings'][] = 'Google Agenda: '.$e->getMessage(); }

        try {
            $accounts = Http::withToken($token)->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts')->throw()->json('accounts', []);
            $result['business_accounts'] = $accounts;
            foreach ($accounts as $googleAccount) {
                $parent = $googleAccount['name'] ?? null;
                if (!$parent) continue;
                $locations = Http::withToken($token)->get("https://mybusinessbusinessinformation.googleapis.com/v1/{$parent}/locations", [
                    'readMask'=>'name,title,storeCode,websiteUri,phoneNumbers,storefrontAddress,latlng,categories,metadata',
                    'pageSize'=>100,
                ])->throw()->json('locations', []);
                foreach ($locations as $location) $result['business_locations'][] = $location;
            }
            if (count($result['business_locations']) === 1) {
                $this->applyLocation($account, $result['business_locations'][0]);
                $result['company_updated'] = true;
            }
        } catch (Throwable $e) { $result['warnings'][] = 'Perfil da Empresa Google: '.$e->getMessage(); }

        $meta = $account->metadata ?? [];
        $meta['google_sync'] = $result;
        $account->update(['metadata'=>$meta]);
        return $result;
    }

    public function applyLocation(SocialAccount $account, array $location): void
    {
        $a = $location['storefrontAddress'] ?? [];
        $lines = $a['addressLines'] ?? [];
        $empresa = $account->empresa;
        $empresa->update(array_filter([
            'nome_fantasia'=>$location['title'] ?? null,
            'telefone'=>data_get($location, 'phoneNumbers.primaryPhone'),
            'site'=>$location['websiteUri'] ?? null,
            'cep'=>$a['postalCode'] ?? null,
            'logradouro'=>$lines[0] ?? null,
            'complemento'=>isset($lines[1]) ? implode(', ', array_slice($lines, 1)) : null,
            'bairro'=>$a['sublocality'] ?? null,
            'cidade'=>$a['locality'] ?? null,
            'estado'=>$a['administrativeArea'] ?? null,
        ], fn($v)=>$v!==null && $v!==''));
        $meta = $account->metadata ?? [];
        $meta['selected_business_location'] = $location;
        $account->update(['metadata'=>$meta]);
    }
}
