<?php

namespace Tests\Unit;

use App\Models\NfceCsc;
use App\Services\Fiscal\NfceXmlSupplementService;
use ReflectionClass;
use Tests\TestCase;

class NfceCscTest extends TestCase
{
    public function test_servico_le_codigo_csc_pelo_accessor_descriptografado(): void
    {
        $csc = new NfceCsc(['identificador' => '1']);
        $csc->codigo = 'CSC-SEGURO-DE-TESTE';

        $servico = new NfceXmlSupplementService();
        $metodo = (new ReflectionClass($servico))->getMethod('credenciaisCsc');
        [$codigo, $identificador] = $metodo->invoke($servico, $csc);

        self::assertSame('CSC-SEGURO-DE-TESTE', $codigo);
        self::assertSame('000001', $identificador);
        self::assertArrayNotHasKey('codigo', $csc->getAttributes());
        self::assertArrayHasKey('codigo_criptografado', $csc->getAttributes());
    }

    public function test_csc_cifrado_com_outra_chave_pode_ser_recadastrado(): void
    {
        $csc = new NfceCsc();
        $csc->setRawAttributes([
            'identificador' => '1',
            'codigo_criptografado' => 'conteudo-invalido',
        ]);

        self::assertNull($csc->codigo);
        self::assertSame('', $csc->codigo_mascarado);
    }

    public function test_urls_da_nfce_podem_ser_configuradas_pelo_ambiente(): void
    {
        $url = 'https://nfeweb.sefaz.go.gov.br/nfeweb/sites/nfce/danfeNFCe';
        config()->set('services.sefaz.nfce_qrcode_url', $url);
        config()->set('services.sefaz.nfce_consulta_url', $url);

        $servico = new NfceXmlSupplementService();
        $metodo = (new ReflectionClass($servico))->getMethod('urlsDaUf');

        self::assertSame([$url, $url], $metodo->invoke($servico, 'GO', 1));
    }

    public function test_corrige_url_chave_sem_esquema_quando_qrcode_ja_existe(): void
    {
        $url = 'https://nfeweb.sefaz.go.gov.br/nfeweb/sites/nfce/danfeNFCe';
        config()->set('services.sefaz.nfce_qrcode_url', $url);
        config()->set('services.sefaz.nfce_consulta_url', $url);

        $nota = new \App\Models\NotaFiscal([
            'modelo' => '65',
            'ambiente' => 1,
            'emitente_uf' => 'GO',
        ]);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFeSupl>'
            .'<qrCode>https://nfeweb.sefaz.go.gov.br/consulta?p=teste</qrCode>'
            .'<urlChave>www.sefaz.go.gov.br/nfce/consulta</urlChave>'
            .'</infNFeSupl></NFe>';

        $servico = new NfceXmlSupplementService();
        $corrigido = $servico->aplicar($nota, $xml);

        self::assertSame($url, $servico->extrair($corrigido)['url_chave']);
    }
}
