<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\NotaFiscal;
use App\Services\Fiscal\ChaveAcessoService;
use Carbon\Carbon;
use Tests\TestCase;

class ChaveAcessoServiceTest extends TestCase
{
    public function test_gera_chave_de_44_digitos_com_dv_valido(): void
    {
        $empresa = new Empresa(['cnpj' => '44.707.437/0001-35', 'estado' => 'GO']);
        $nfe = new NotaFiscal([
            'emitente_cnpj' => '44.707.437/0001-35', 'modelo' => '55', 'serie' => '1',
            'numero' => 4, 'data_emissao' => Carbon::parse('2026-06-06 22:59:04-03:00'),
        ]);
        $nfe->setRelation('empresa', $empresa);

        $service = new ChaveAcessoService();
        $chave = $service->gerar($nfe, '11252279');

        self::assertMatchesRegularExpression('/^\d{44}$/', $chave);
        self::assertSame('52', substr($chave, 0, 2));
        self::assertSame($service->digitoVerificador(substr($chave, 0, 43)), (int) substr($chave, -1));
    }
}
