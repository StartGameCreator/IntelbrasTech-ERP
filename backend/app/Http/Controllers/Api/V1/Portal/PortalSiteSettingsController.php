<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalSiteSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->settings((int) $request->user()->empresa_id)]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_nome' => 'nullable|string|max:120',
            'seo_titulo' => 'nullable|string|max:180',
            'seo_descricao' => 'nullable|string|max:500',
            'seo_palavras_chave' => 'nullable|string|max:1000',
            'seo_canonical_url' => 'nullable|url|max:500',
            'seo_og_image_url' => 'nullable|url|max:1000',
            'google_site_verification' => 'nullable|string|max:500',
            'google_analytics_id' => ['nullable', 'string', 'max:40', 'regex:/^G-[A-Z0-9]+$/i'],
            'google_analytics_ativo' => 'required|boolean',
            'google_tag_manager_id' => ['nullable', 'string', 'max:40', 'regex:/^GTM-[A-Z0-9]+$/i'],
            'google_tag_manager_ativo' => 'required|boolean',
            'microsoft_clarity_id' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'microsoft_clarity_ativo' => 'required|boolean',
            'meta_pixel_id' => 'nullable|string|max:80|regex:/^[0-9]+$/',
            'meta_pixel_ativo' => 'required|boolean',
            'tiktok_pixel_id' => 'nullable|string|max:80|regex:/^[A-Za-z0-9_-]+$/',
            'tiktok_pixel_ativo' => 'required|boolean',
            'logo_url' => 'nullable|url|max:1000',
            'favicon_url' => 'nullable|url|max:1000',
            'indexar_site' => 'required|boolean',
        ]);

        foreach (['google_analytics' => 'Google Analytics', 'google_tag_manager' => 'Google Tag Manager', 'microsoft_clarity' => 'Microsoft Clarity', 'meta_pixel' => 'Meta Pixel', 'tiktok_pixel' => 'TikTok Pixel'] as $key => $label) {
            if (($data["{$key}_ativo"] ?? false) && empty($data["{$key}_id"])) {
                return response()->json(['message' => "Informe o identificador do {$label} antes de ativá-lo.", 'errors' => ["{$key}_id" => ["Informe o identificador do {$label} antes de ativá-lo."]]], 422);
            }
        }

        $empresaId = (int) $request->user()->empresa_id;
        $existing = DB::table('portal_site_settings')->where('empresa_id', $empresaId)->exists();
        if ($existing) {
            DB::table('portal_site_settings')->where('empresa_id', $empresaId)->update(array_merge($data, ['updated_at' => now()]));
        } else {
            DB::table('portal_site_settings')->insert(array_merge($data, ['empresa_id' => $empresaId, 'created_at' => now(), 'updated_at' => now()]));
        }

        return response()->json(['message' => 'SEO e rastreamento atualizados.', 'data' => $this->settings($empresaId)]);
    }

    private function settings(int $empresaId): object
    {
        return DB::table('portal_site_settings')->where('empresa_id', $empresaId)->first()
            ?? (object) [
                'site_nome' => null, 'seo_titulo' => null, 'seo_descricao' => null,
                'seo_palavras_chave' => null, 'seo_canonical_url' => null, 'seo_og_image_url' => null,
                'google_site_verification' => null, 'meta_pixel_id' => null, 'meta_pixel_ativo' => false,
                'google_analytics_id' => null, 'google_analytics_ativo' => false,
                'google_tag_manager_id' => null, 'google_tag_manager_ativo' => false,
                'microsoft_clarity_id' => null, 'microsoft_clarity_ativo' => false,
                'tiktok_pixel_id' => null, 'tiktok_pixel_ativo' => false, 'logo_url' => null,
                'favicon_url' => null, 'indexar_site' => true,
            ];
    }
}
