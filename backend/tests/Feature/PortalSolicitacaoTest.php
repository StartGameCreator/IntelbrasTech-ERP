<?php

namespace Tests\Feature;

use App\Models\{CrmAtividade, Lead, PortalSolicitacao};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PortalSolicitacaoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_simulacao_solar_so_confirma_depois_de_gravar_no_crm(): void
    {
        $response = $this->postJson('/api/v1/portal/solicitacoes', [
            'tipo' => 'solar',
            'nome' => 'Teste automatizado portal',
            'telefone' => '61999999999',
            'email' => 'portal-teste@example.test',
            'cidade' => 'Luziânia',
            'uf' => 'GO',
            'municipio_ibge' => '5212501',
            'cep' => '72801-630',
            'consumo_kwh' => 650,
            'potencia_estimada_kwp' => 5.51,
            'consentimento' => true,
            'portal_guard' => '',
        ]);

        $response->assertCreated()->assertJsonStructure(['message', 'protocolo', 'lead_id']);

        $solicitacao = PortalSolicitacao::query()->where('uuid', $response->json('protocolo'))->first();
        $lead = Lead::withoutGlobalScopes()->where('uuid', $response->json('lead_id'))->first();

        $this->assertNotNull($solicitacao);
        $this->assertNotNull($lead);
        $this->assertSame($lead->id, $solicitacao->lead_id);
        $this->assertSame('portal', $lead->origem);
        $this->assertSame('Pré-simulação de energia solar', $lead->titulo);
        $this->assertNotNull($lead->entrou_etapa_em);
        $this->assertGreaterThan(0, $lead->score);
        $this->assertSame('Realizar primeiro contato', $lead->proxima_acao);
        $this->assertNotNull($lead->proxima_acao_em);
        $this->assertTrue(CrmAtividade::query()
            ->where('lead_id', $lead->id)
            ->where('titulo', 'Lead recebido pelo portal')
            ->exists());
    }

    public function test_honeypot_nunca_retorna_falso_sucesso(): void
    {
        $this->postJson('/api/v1/portal/solicitacoes', [
            'tipo' => 'solar',
            'nome' => 'Robô',
            'telefone' => '61999999999',
            'consentimento' => true,
            'portal_guard' => 'preenchido',
        ])->assertUnprocessable()->assertJsonMissing(['protocolo']);
    }
}
