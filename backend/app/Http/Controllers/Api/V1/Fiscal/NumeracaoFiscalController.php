<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\NotaFiscal;
use App\Models\SerieFiscal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NumeracaoFiscalController extends Controller
{
    public function show(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        return response()->json([
            'nfe' => $this->dadosSerie($empresaId, 'NFe'),
            'nfce' => $this->dadosSerie($empresaId, 'NFCe'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'tipo' => 'required|in:NFe,NFCe',
            'serie' => 'required|string|max:5',
            'proximo_numero' => 'required|integer|min:1|max:999999999',
            'ambiente' => 'nullable|integer|in:1,2',
        ]);

        $empresaId = auth()->user()->empresa_id;
        $tipo = $dados['tipo'];
        $serieInformada = ltrim((string) $dados['serie'], '0') ?: '0';
        $proximo = (int) $dados['proximo_numero'];

        $maiorNumeroRegistrado = (int) NotaFiscal::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('serie', $serieInformada)
            ->max('numero');

        $serie = DB::transaction(function () use ($empresaId, $dados, $tipo, $serieInformada, $proximo, $maiorNumeroRegistrado) {
            $serie = SerieFiscal::query()
                ->where('empresa_id', $empresaId)
                ->where('tipo', $tipo)
                ->where('serie', $serieInformada)
                ->lockForUpdate()
                ->first();

            $maiorConsumido = max((int) ($serie?->ultimo_numero ?? 0), $maiorNumeroRegistrado);

            if ($proximo <= $maiorConsumido) {
                throw ValidationException::withMessages([
                    'proximo_numero' => "O próximo número deve ser maior que {$maiorConsumido}; a numeração fiscal não pode retroceder.",
                ]);
            }

            if (!$serie) {
                $serie = SerieFiscal::create([
                    'empresa_id' => $empresaId,
                    'tipo' => $tipo,
                    'serie' => $serieInformada,
                    'ultimo_numero' => $proximo - 1,
                    'ambiente' => $dados['ambiente'] ?? 2,
                    'ativo' => true,
                ]);
            } else {
                $serie->update([
                    'ultimo_numero' => $proximo - 1,
                    'ambiente' => $dados['ambiente'] ?? $serie->ambiente,
                    'ativo' => true,
                ]);
            }

            return $serie->fresh();
        });

        return response()->json([
            'message' => "Próximo número da {$tipo} definido como {$proximo}.",
            'serie' => [
                'tipo' => $serie->tipo,
                'serie' => $serie->serie,
                'ultimo_numero' => (int) $serie->ultimo_numero,
                'proximo_numero' => (int) $serie->ultimo_numero + 1,
                'ambiente' => (int) $serie->ambiente,
            ],
        ]);
    }

    private function dadosSerie(int $empresaId, string $tipo): ?array
    {
        $serie = SerieFiscal::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('ativo', true)
            ->orderBy('id')
            ->first();

        if (!$serie) {
            return null;
        }

        return [
            'tipo' => $serie->tipo,
            'serie' => $serie->serie,
            'ultimo_numero' => (int) $serie->ultimo_numero,
            'proximo_numero' => (int) $serie->ultimo_numero + 1,
            'ambiente' => (int) $serie->ambiente,
        ];
    }
}
