<?php

namespace App\Services\Crm;

use App\Models\{CrmAtividade, Lead};

class CrmLeadIntakeService
{
    public function registrar(Lead $lead, ?int $usuarioId = null, string $titulo = 'Lead criado'): Lead
    {
        $score = 0;
        foreach (['email', 'telefone', 'empresa_nome', 'cnpj'] as $campo) {
            if (! empty($lead->{$campo})) {
                $score += 8;
            }
        }

        $lead->forceFill([
            'entrou_etapa_em' => $lead->entrou_etapa_em ?: now(),
            'score' => max((int) $lead->score, $score),
        ])->save();

        CrmAtividade::firstOrCreate(
            ['lead_id' => $lead->id, 'tipo' => 'sistema', 'titulo' => $titulo],
            [
                'empresa_id' => $lead->empresa_id,
                'usuario_id' => $usuarioId,
                'descricao' => $lead->titulo,
                'status' => 'concluida',
                'concluida_em' => now(),
            ],
        );

        app(CrmAutomationService::class)->executar('lead_criado', $lead);
        $lead->refresh();

        if (! $lead->proxima_acao_em) {
            $lead->update([
                'proxima_acao' => 'Realizar primeiro contato',
                'proxima_acao_em' => now()->addDay(),
            ]);
        }

        return $lead->fresh(['etapa', 'responsavel']);
    }
}
