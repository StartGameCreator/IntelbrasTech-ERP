<?php

namespace Tests\Unit;

use App\Models\{Empresa, NotaFiscal, NotaFiscalItem};
use App\Services\Fiscal\XmlNFeGenerator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class XmlNFeGeneratorTest extends TestCase
{
    public function test_gera_xml_mei_csosn_400_no_layout_400(): void
    {
        $empresa = new Empresa([
            'cnpj'=>'44.707.437/0001-35','estado'=>'GO','regime_tributario'=>1,'ibge'=>'5200100',
            'regime_fiscal_confirmado'=>true,'regime_apuracao'=>'mei','icms_csosn_padrao'=>'400',
            'pis_cst_padrao'=>'49','cofins_cst_padrao'=>'49',
            'logradouro'=>'Rua Teste','numero'=>'1','bairro'=>'Centro','cidade'=>'Goiânia','cep'=>'74000000',
        ]);
        $nfe = new NotaFiscal([
            'chave_acesso'=>str_repeat('1', 44),'emitente_cnpj'=>$empresa->cnpj,'emitente_ie'=>'108800539',
            'emitente_razao'=>'Empresa Teste','natureza_operacao'=>'Padrão MEI','modelo'=>'55','serie'=>'1','numero'=>4,
            'data_emissao'=>Carbon::parse('2026-06-06 22:59:04-03:00'),'ambiente'=>2,'finalidade'=>1,
            'destinatario_cpf_cnpj'=>'25824554153','destinatario_nome'=>'José Carlos dos Santos',
            'destinatario_logradouro'=>'SQ 17','destinatario_numero'=>'92','destinatario_bairro'=>'Centro',
            'destinatario_ibge'=>'4106902','destinatario_cidade'=>'Curitiba','destinatario_uf'=>'PR','destinatario_cep'=>'80000000',
            'valor_produtos'=>3699,'valor_total'=>3699,'tipo_frete'=>'9',
        ]);
        $item = new NotaFiscalItem([
            'numero_item'=>1,'codigo'=>'365214269','descricao'=>'TV 65 polegadas','ncm'=>'85287200','cfop'=>'5102',
            'unidade'=>'UN','quantidade'=>1,'valor_unitario'=>3699,'valor_total'=>3699,'icms_origem'=>0,'icms_cst'=>'400',
            'pis_cst'=>'49','cofins_cst'=>'49',
        ]);
        $nfe->setRelation('empresa', $empresa);
        $nfe->setRelation('itens', new Collection([$item]));

        $xml = app(XmlNFeGenerator::class)->gerar($nfe);

        self::assertStringContainsString('versao="4.00"', $xml);
        self::assertStringContainsString('<ICMSSN102><orig>0</orig><CSOSN>400</CSOSN></ICMSSN102>', $xml);
        self::assertStringNotContainsString('<ICMSSN400>', $xml);
        self::assertStringContainsString('<idDest>2</idDest>', $xml);
        self::assertStringContainsString('<CFOP>6102</CFOP>', $xml);
        self::assertStringNotContainsString('<CFOP>5102</CFOP>', $xml);
        self::assertStringContainsString('<modFrete>9</modFrete>', $xml);
        self::assertNotFalse(simplexml_load_string($xml));
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        self::assertFalse($dom->schemaValidate(base_path(
            'vendor/nfephp-org/sped-nfe/schemes/PL_010_V1.30/nfe_v4.00.xsd'
        )));
        $erros = array_map(fn ($erro) => trim($erro->message), libxml_get_errors());
        self::assertNotEmpty($erros);
        self::assertStringContainsString('Signature', implode(' | ', $erros));
        self::assertStringNotContainsString('ICMSSN', implode(' | ', $erros));
        libxml_clear_errors();
    }
}
