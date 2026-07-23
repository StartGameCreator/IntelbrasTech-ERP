<?php

namespace App\Services\Fiscal;

use App\Models\CertificadoDigital;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{Log, Storage};
use NFePHP\Common\Certificate;
use NFePHP\NFe\{Complements, Tools};
use NFePHP\NFe\Common\Standardize;

/**
 * Camada de integração com os Web Services da SEFAZ.
 *
 * IMPORTANTE: esta classe isola toda a comunicação real com a SEFAZ para que
 * o restante do sistema (NFeService, Controllers, Jobs) nunca precise saber
 * como o envelope SOAP é montado. Hoje os métodos operam em modo SIMULADO
 * (mock de homologação), o que permite todo o fluxo de emissão/cancelamento/
 * CC-e funcionar de ponta a ponta em desenvolvimento sem certificado real.
 *
 * Para produção, troque o corpo de cada método pela chamada real usando uma
 * biblioteca madura de mercado — as mais usadas no ecossistema Laravel/PHP são:
 *   - nfephp-org/sped-nfe   (https://github.com/nfephp-org/sped-nfe)
 *   - nfephp-org/sped-common
 * Essas libs cuidam de: montagem do envelope SOAP, assinatura XML-DSig,
 * comunicação com os webservices por UF, contingência (SVC-AN/SVC-RS) e
 * schemas de validação (XSD) por versão do layout NF-e (atualmente 4.00).
 */
class SefazService
{
    public function modo(): string
    {
        return (string) config('services.sefaz.driver', 'fake');
    }

    public function assertDisponivel(): void
    {
        if (! in_array($this->modo(), ['fake', 'nfephp'], true)) {
            throw new \RuntimeException(
                'Driver fiscal inválido. Use FISCAL_DRIVER=fake para testes ou FISCAL_DRIVER=nfephp para a SEFAZ.'
            );
        }

        if ($this->modo() === 'fake' && app()->environment('production') && ! config('services.sefaz.allow_fake_in_production', false)) {
            throw new \RuntimeException(
                'Emissão simulada bloqueada em produção. Configure um driver fiscal real.'
            );
        }

        if ($this->modo() === 'nfephp') {
            foreach (['openssl', 'dom', 'simplexml', 'libxml', 'soap'] as $extensao) {
                if (! extension_loaded($extensao)) {
                    throw new \RuntimeException("Extensão PHP obrigatória para a SEFAZ ausente: {$extensao}.");
                }
            }
        }
    }

    /**
     * Assina digitalmente o XML da NF-e com o certificado A1/A3 da empresa.
     *
     * Em produção: carregar o certificado (openssl_pkcs12_read do .pfx),
     * localizar a tag <infNFe>, gerar o digest (SHA-1) e assinar conforme
     * XML-DSig (padrão exigido pela SEFAZ).
     */
    public function assinarXml(string $xml, CertificadoDigital $certificado): string
    {
        $this->assertDisponivel();
        if (! $this->certificadoValido($certificado)) {
            throw new \RuntimeException('Certificado digital inválido, vencido ou não configurado.');
        }

        if ($this->modo() === 'nfephp') {
            return $this->tools($certificado, 2)->signNFe($xml, 1);
        }

        // Simulação: injeta um bloco <Signature> fictício para manter o XML
        // navegável durante o desenvolvimento, sem validade jurídica real.
        $assinaturaFake = sprintf(
            '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><!-- assinatura simulada (%s) --></Signature>',
            now()->toIso8601String()
        );

        return str_replace('</infNFe>', '</infNFe>' . $assinaturaFake, $xml);
    }

    /**
     * Envia o XML assinado para o webservice de autorização (NFeAutorizacao)
     * da UF do emitente e retorna o resultado do processamento.
     */
    public function enviarNFe(string $xmlAssinado, int $ambiente, ?string $uf = null): array
    {
        $this->assertDisponivel();
        if ($this->modo() === 'nfephp') {
            $certificado = $this->certificadoAtivo();
            $tools = $this->tools($certificado, $ambiente, $uf);
            $lote = str_pad((string) random_int(1, 999999999999999), 15, '0', STR_PAD_LEFT);
            $respostaXml = $tools->sefazEnviaLote([$xmlAssinado], $lote, 1);
            $std = (new Standardize($respostaXml))->toStd();
            $protocolo = $std->protNFe->infProt ?? null;

            if (! $protocolo && isset($std->infRec->nRec)) {
                $respostaXml = $tools->sefazConsultaRecibo((string) $std->infRec->nRec, $ambiente);
                $std = (new Standardize($respostaXml))->toStd();
                $protocolo = $std->protNFe->infProt ?? null;
            }

            if (! $protocolo) {
                return [
                    'cStat' => (string) ($std->cStat ?? '999'),
                    'xMotivo' => (string) ($std->xMotivo ?? 'Resposta da SEFAZ sem protocolo de autorização.'),
                    'xml_retorno' => $respostaXml,
                ];
            }

            $resultado = [
                'cStat' => (string) $protocolo->cStat,
                'xMotivo' => (string) $protocolo->xMotivo,
                'chNFe' => (string) $protocolo->chNFe,
                'nProt' => (string) ($protocolo->nProt ?? ''),
                'dhRecbto' => (string) ($protocolo->dhRecbto ?? now()->toIso8601String()),
                'tpAmb' => $ambiente,
                'xml_retorno' => $respostaXml,
            ];
            if ($resultado['cStat'] === '100') {
                $resultado['xml'] = Complements::toAuthorize($xmlAssinado, $respostaXml);
            }
            return $resultado;
        }
        Log::info('SefazService::enviarNFe (modo simulado)', ['ambiente' => $ambiente, 'uf' => $uf]);

        // Simulação de resposta de autorização (cStat 100 = Autorizado o uso da NF-e).
        return [
            'cStat'   => '100',
            'xMotivo' => 'Autorizado o uso da NF-e',
            'chNFe'   => preg_match('/Id="NFe(\d{44})"/', $xmlAssinado, $m)
                ? $m[1]
                : $this->gerarChaveSimulada($uf),
            'nProt'   => $this->gerarProtocoloSimulado($ambiente),
            'dhRecbto'=> now()->toIso8601String(),
        ];
    }

    /**
     * Solicita o cancelamento de uma NF-e já autorizada.
     */
    public function cancelarNFe(string $chave, string $protocolo, string $justificativa, CertificadoDigital $certificado, int $ambiente): array
    {
        $this->assertDisponivel();
        if (! $this->certificadoValido($certificado)) {
            throw new \RuntimeException('Certificado digital inválido, vencido ou não configurado.');
        }

        if ($this->modo() === 'nfephp') {
            $xml = $this->tools($certificado, $ambiente)->sefazCancela($chave, $justificativa, $protocolo);
            return $this->retornoEvento($xml);
        }

        Log::info('SefazService::cancelarNFe (modo simulado)', ['chave' => $chave]);

        return [
            'cStat'   => '135',
            'xMotivo' => 'Evento registrado e vinculado a NF-e',
            'nProt'   => $this->gerarProtocoloSimulado($ambiente),
            'xml'     => "<procEventoNFe><!-- cancelamento simulado --><chNFe>{$chave}</chNFe><justificativa>{$justificativa}</justificativa></procEventoNFe>",
        ];
    }

    /**
     * Registra uma Carta de Correção Eletrônica (evento 110110).
     */
    public function cartaCorrecao(string $chave, string $descricao, int $sequencia, CertificadoDigital $certificado, int $ambiente): array
    {
        $this->assertDisponivel();
        if (! $this->certificadoValido($certificado)) {
            throw new \RuntimeException('Certificado digital inválido, vencido ou não configurado.');
        }

        if ($this->modo() === 'nfephp') {
            $xml = $this->tools($certificado, $ambiente)->sefazCCe($chave, $descricao, $sequencia);
            return $this->retornoEvento($xml);
        }

        Log::info('SefazService::cartaCorrecao (modo simulado)', ['chave' => $chave, 'sequencia' => $sequencia]);

        return [
            'cStat'   => '135',
            'xMotivo' => 'Evento registrado e vinculado a NF-e',
            'nProt'   => $this->gerarProtocoloSimulado($ambiente),
        ];
    }

    /**
     * Consulta a situação atual de uma NF-e na SEFAZ pela chave de acesso.
     */
    public function consultarNFe(string $chave, CertificadoDigital $certificado): array
    {
        $this->assertDisponivel();
        if ($this->modo() === 'nfephp') {
            $xml = $this->tools($certificado, config('services.sefaz.ambiente', 2))->sefazConsultaChave($chave);
            $std = (new Standardize($xml))->toStd();
            $protocolo = $std->protNFe->infProt ?? null;
            return [
                'cStat' => (string) ($protocolo->cStat ?? $std->cStat ?? '999'),
                'xMotivo' => (string) ($protocolo->xMotivo ?? $std->xMotivo ?? 'Resposta não identificada.'),
                'chNFe' => (string) ($protocolo->chNFe ?? $chave),
                'nProt' => (string) ($protocolo->nProt ?? ''),
                'xml' => $xml,
            ];
        }
        Log::info('SefazService::consultarNFe (modo simulado)', ['chave' => $chave]);

        return [
            'cStat'   => '100',
            'xMotivo' => 'Autorizado o uso da NF-e',
            'chNFe'   => $chave,
        ];
    }

    public function statusServico(CertificadoDigital $certificado, int $ambiente): array
    {
        $this->assertDisponivel();
        if ($this->modo() === 'fake') {
            return ['cStat' => '107', 'xMotivo' => 'Serviço em Operação (simulado)', 'tpAmb' => $ambiente];
        }
        $xml = $this->tools($certificado, $ambiente)->sefazStatus('', $ambiente);
        $std = (new Standardize($xml))->toStd();
        return [
            'cStat' => (string) ($std->cStat ?? '999'),
            'xMotivo' => (string) ($std->xMotivo ?? 'Resposta não identificada.'),
            'tpAmb' => (int) ($std->tpAmb ?? $ambiente),
            'dhRecbto' => (string) ($std->dhRecbto ?? ''),
        ];
    }

    // ── Helpers privados ──────────────────────────────────────

    private function certificadoValido(CertificadoDigital $certificado): bool
    {
        return $certificado->ativo && $certificado->validade?->isFuture();
    }

    private function certificadoAtivo(): CertificadoDigital
    {
        return CertificadoDigital::where('ativo', true)->where('validade', '>', now())->first()
            ?? throw new \RuntimeException('Nenhum certificado digital A1 válido está ativo.');
    }

    private function tools(CertificadoDigital $certificado, int $ambiente, ?string $uf = null): Tools
    {
        $this->assertDisponivel();
        if ($certificado->tipo !== 'A1') {
            throw new \RuntimeException('A integração direta com a SEFAZ exige certificado A1 em arquivo PFX/P12.');
        }
        if (! $certificado->arquivo || ! Storage::disk('local')->exists($certificado->arquivo)) {
            throw new \RuntimeException('Arquivo PFX/P12 do certificado A1 não foi encontrado.');
        }
        $empresa = $certificado->empresa;
        $conteudo = Storage::disk('local')->get($certificado->arquivo);
        $certificate = Certificate::readPfx($conteudo, (string) $certificado->senha);
        $config = json_encode([
            'atualizacao' => now()->format('Y-m-d H:i:s'),
            'tpAmb' => $ambiente,
            'razaosocial' => $empresa->razao_social,
            'cnpj' => preg_replace('/\D/', '', (string) $empresa->cnpj),
            'siglaUF' => strtoupper($uf ?: $empresa->estado),
            'schemes' => config('services.sefaz.schemas', 'PL_010_V1.30'),
            'versao' => '4.00',
            'tokenIBPT' => '', 'CSC' => '', 'CSCid' => '',
            'proxyConf' => [
                'proxyIp' => config('services.sefaz.proxy.ip', ''),
                'proxyPort' => config('services.sefaz.proxy.port', ''),
                'proxyUser' => config('services.sefaz.proxy.user', ''),
                'proxyPass' => config('services.sefaz.proxy.password', ''),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $tools = new Tools($config, $certificate);
        $tools->model('55');
        $tools->setVerAplic('IntelbrasTech-ERP-1.0');
        return $tools;
    }

    private function retornoEvento(string $xml): array
    {
        $std = (new Standardize($xml))->toStd();
        $evento = $std->retEvento->infEvento ?? null;
        if (is_array($evento)) $evento = $evento[0] ?? null;
        return [
            'cStat' => (string) ($evento->cStat ?? $std->cStat ?? '999'),
            'xMotivo' => (string) ($evento->xMotivo ?? $std->xMotivo ?? 'Resposta não identificada.'),
            'nProt' => (string) ($evento->nProt ?? ''),
            'xml' => $xml,
        ];
    }

    private function gerarChaveSimulada(?string $uf = null): string
    {
        // Chave de acesso tem 44 dígitos numéricos. Aqui apenas simulamos o formato,
        // concatenando blocos de dígitos aleatórios (random_int não suporta 44 dígitos de uma vez).
        $digitos = '';
        for ($i = 0; $i < 44; $i++) {
            $digitos .= (string) random_int(0, 9);
        }
        return $digitos;
    }

    private function gerarProtocoloSimulado(int $ambiente): string
    {
        return $ambiente . now()->format('YmdHis') . random_int(100, 999);
    }
}
