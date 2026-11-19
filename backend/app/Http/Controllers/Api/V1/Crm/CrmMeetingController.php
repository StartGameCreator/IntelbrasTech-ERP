<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\Social\CrmMeetingProviderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrmMeetingController extends Controller
{
    public function status(Request $request, CrmMeetingProviderService $service): JsonResponse
    {
        return response()->json(['data' => $service->status((int) $request->user()->id)]);
    }

    public function store(Request $request, CrmMeetingProviderService $service): JsonResponse
    {
        $data = $request->validate([
            'provider' => 'required|in:google,microsoft',
            'titulo' => 'required|string|max:180',
            'descricao' => 'nullable|string|max:5000',
            'inicio' => 'required|date',
            'fim' => 'required|date|after:inicio',
            'timezone' => 'nullable|string|max:80',
            'participantes' => 'nullable|array|max:50',
            'participantes.*' => 'required|email:rfc|max:180',
            'lead_id' => 'nullable|integer',
            'cliente_nome' => 'nullable|string|max:180',
            'cliente_email' => 'nullable|email:rfc|max:180',
        ]);

        $data['timezone'] = $data['timezone'] ?: config('app.timezone', 'America/Sao_Paulo');
        $account = SocialAccount::query()
            ->where('usuario_id', $request->user()->id)
            ->where('provider', $data['provider'])
            ->where('ativo', true)
            ->first();

        if (!$account) {
            return response()->json([
                'message' => 'Conecte sua conta '.($data['provider'] === 'google' ? 'Google' : 'Microsoft').' na Central de APIs antes de criar a reunião.',
                'code' => 'SOCIAL_ACCOUNT_NOT_CONNECTED',
            ], 422);
        }

        try {
            $meeting = $service->create($account, $data);

            $id = DB::table('crm_meetings')->insertGetId([
                'empresa_id' => $request->user()->empresa_id,
                'usuario_id' => $request->user()->id,
                'lead_id' => $data['lead_id'] ?? null,
                'provider' => $data['provider'],
                'external_id' => $meeting['external_id'] ?? null,
                'titulo' => $data['titulo'],
                'descricao' => $data['descricao'] ?? null,
                'cliente_nome' => $data['cliente_nome'] ?? null,
                'cliente_email' => $data['cliente_email'] ?? null,
                'participantes' => json_encode($data['participantes'] ?? []),
                'inicio' => Carbon::parse($data['inicio']),
                'fim' => Carbon::parse($data['fim']),
                'timezone' => $data['timezone'],
                'join_url' => $meeting['join_url'] ?? null,
                'event_url' => $meeting['event_url'] ?? null,
                'status' => 'agendada',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Reunião criada e adicionada ao calendário da conta conectada.',
                'data' => array_merge($meeting, ['id' => $id]),
            ], 201);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
