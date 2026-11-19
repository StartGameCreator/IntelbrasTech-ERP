# Auditoria do Motor Fiscal — IntelbrasTech ERP

- Executada em: 21/07/2026 13:52:26
- Projeto: `D:\Skill\IntelbrasTech-ERP`
- Modo: somente leitura
- Checkpoint: `CP-2026.07.21-F14-2-1-AUDITORIA-CORRIGIDA`

## Objetivo

Identificar qual classe gera o XML enviado no fluxo PDV → NFC-e → SEFAZ.

## 1. Ocorrências relevantes

Total: **87**

Arquivo: `OCORRENCIAS_MOTOR_FISCAL.csv`

### `ICMSSN400`

- `app\Services\Fiscal\XmlNFeGenerator.php:177` — `'400' => '<ICMSSN400><orig>'.$origem.'</orig><CSOSN>400</CSOSN></ICMSSN400>',`

### `<CRT>`

- `app\Services\Fiscal\XmlNFeGenerator.php:102` — `.'<CRT>'.$crt.'</CRT>'`

### `XmlNFeGenerator`

- `app\Services\Fiscal\NFeService.php:345` — `return app(XmlNFeGenerator::class)->gerar($nfe);`
- `app\Services\Fiscal\XmlNFeGenerator.php:9` — `class XmlNFeGenerator`
- `tests\Unit\XmlNFeGeneratorTest.php:6` — `use App\Services\Fiscal\XmlNFeGenerator;`
- `tests\Unit\XmlNFeGeneratorTest.php:11` — `class XmlNFeGeneratorTest extends TestCase`
- `tests\Unit\XmlNFeGeneratorTest.php:36` — `$xml = (new XmlNFeGenerator())->gerar($nfe);`

### `new Make`

_Nenhuma ocorrência._

### `tagICMS`

_Nenhuma ocorrência._

### `assinarXml`

- `app\Services\Fiscal\NFeService.php:116` — `$xmlAssinado = $this->sefaz->assinarXml(`
- `app\Services\Fiscal\SefazService.php:67` — `public function assinarXml(`

### `enviarNFe`

- `app\Services\Fiscal\NFeService.php:125` — `$retorno = $this->sefaz->enviarNFe(`
- `app\Services\Fiscal\SefazService.php:102` — `public function enviarNFe(`
- `app\Services\Fiscal\SefazService.php:149` — `Log::info('SefazService::enviarNFe (modo simulado)', ['ambiente' => $ambiente, 'uf' => $uf]);`

## 2. Rotas

Consulte `ROTAS_FISCAIS.txt`.

## 3. Runtime Laravel

Consulte `RUNTIME_FISCAL.txt`.

## 4. Sintaxe

Consulte `VALIDACAO_SINTAXE.txt`.

## 5. Logs

Consulte `ULTIMOS_LOGS_FISCAIS.txt`.

## 6. Snapshot

`SNAPSHOT_MOTOR_FISCAL.zip` — sem .env, banco, certificado, CSC ou senhas.

## 7. Diagnóstico automático

## 8. Próximo passo

Envie `RELATORIO_AUDITORIA_MOTOR_FISCAL.md` e `SNAPSHOT_MOTOR_FISCAL.zip`.

Pasta: `D:\Skill\IntelbrasTech-ERP\auditorias\motor-fiscal-20260721-135226`
