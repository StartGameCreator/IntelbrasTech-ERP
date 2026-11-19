<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\Cadastros\ClienteController;
use App\Models\Cliente;
use App\Services\Fiscal\NFeService;
use App\Services\Fiscal\PdvNfceService;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class EnderecoFiscalTest extends TestCase
{
    public function test_cep_completa_codigo_ibge_ausente_no_cadastro(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'cep' => '85811-410',
                'logradouro' => 'Rua Alcir da Motta',
                'bairro' => 'Cancelli',
                'localidade' => 'Cascavel',
                'uf' => 'PR',
                'ibge' => '4104808',
            ]),
        ]);

        $metodo = (new ReflectionClass(ClienteController::class))
            ->getMethod('completarEnderecoFiscal');
        $resultado = $metodo->invoke(new ClienteController(), [
            'cep' => '85811410',
            'cidade' => 'Cascavel',
            'estado' => 'PR',
            'ibge' => null,
        ]);

        self::assertSame('4104808', $resultado['ibge']);
        self::assertSame('Cascavel', $resultado['cidade']);
        self::assertSame('PR', $resultado['estado']);
    }

    public function test_endereco_vazio_do_pedido_usa_ibge_do_cliente(): void
    {
        $servico = (new ReflectionClass(NFeService::class))->newInstanceWithoutConstructor();
        $metodo = (new ReflectionClass(NFeService::class))->getMethod('primeiroPreenchido');

        self::assertSame('4104808', $metodo->invoke($servico, '', '4104808'));
        self::assertSame('4104808', $metodo->invoke($servico, null, '4104808'));
    }

    public function test_dados_da_nfce_sao_ressincronizados_do_cliente(): void
    {
        $cliente = new Cliente([
            'razao_social' => 'Cliente Teste',
            'cep' => '85811410',
            'cidade' => 'Cascavel',
            'estado' => 'PR',
            'ibge' => '4104808',
        ]);
        $servico = (new ReflectionClass(PdvNfceService::class))->newInstanceWithoutConstructor();
        $metodo = (new ReflectionClass(PdvNfceService::class))->getMethod('dadosDestinatario');
        $dados = $metodo->invoke($servico, $cliente);

        self::assertSame('4104808', $dados['destinatario_ibge']);
        self::assertSame('Cascavel', $dados['destinatario_cidade']);
        self::assertSame('PR', $dados['destinatario_uf']);
    }
}
