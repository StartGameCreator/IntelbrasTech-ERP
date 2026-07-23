<?php

namespace App\Services\Fiscal;

use App\Models\NfceCsc;
use App\Models\NotaFiscal;
use DOMDocument;
use NFePHP\NFe\Factories\QRCode;
use RuntimeException;
use SimpleXMLElement;

class NfceXmlSupplementService
{
    public function aplicar(NotaFiscal $nota, string $xmlAssinado): string
    {
        if ((string) $nota->modelo !== '65') {
            return $xmlAssinado;
        }

        if ($this->extrair($xmlAssinado)['qr_code_url']) {
            return $xmlAssinado;
        }

        $csc = NfceCsc::query()
            ->where('empresa_id', $nota->empresa_id)
            ->where('ambiente', (int) $nota->ambiente)
            ->where('ativo', true)
            ->latest('updated_at')
            ->first();

        if (! $csc) {
            throw new RuntimeException('Nenhum CSC ativo foi encontrado para gerar o QR Code da NFC-e.');
        }

        [$token, $idToken] = $this->credenciaisCsc($csc->getAttributes());
        [$urlQr, $urlChave] = $this->urlsDaUf(
            strtoupper((string) ($nota->emitente_uf ?: $nota->empresa?->estado)),
            (int) $nota->ambiente
        );

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (! @$dom->loadXML($xmlAssinado, LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
            throw new RuntimeException('O XML assinado da NFC-e é inválido e não permite incluir o QR Code.');
        }

        // A versão 2 é a suportada pela dependência nfephp-org/sped-nfe 5.2.x
        // instalada no checkpoint analisado. O valor pode ser atualizado quando
        // o motor fiscal for migrado para uma versão compatível com QR Code v3.
        return QRCode::putQRTag($dom, $token, $idToken, '200', $urlQr, $urlChave);
    }

    public function extrair(?string $xml): array
    {
        if (! is_string($xml) || trim($xml) === '') {
            return ['qr_code_url' => null, 'url_chave' => null];
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $sx = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NOBLANKS);
            if (! $sx) {
                return ['qr_code_url' => null, 'url_chave' => null];
            }

            $qr = $sx->xpath('//*[local-name()="qrCode"]');
            $chave = $sx->xpath('//*[local-name()="urlChave"]');

            return [
                'qr_code_url' => $this->urlSegura(isset($qr[0]) ? (string) $qr[0] : null),
                'url_chave' => $this->urlSegura(isset($chave[0]) ? (string) $chave[0] : null),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function credenciaisCsc(array $dados): array
    {
        $token = $this->primeiro($dados, ['csc', 'codigo_csc', 'token', 'codigo', 'valor', 'secret']);
        $id = $this->primeiro($dados, ['identificador', 'id_token', 'csc_id', 'id_csc', 'codigo_identificador', 'token_id']);

        if ($token === '' || $id === '') {
            throw new RuntimeException(
                'O cadastro do CSC foi localizado, mas os campos de código e identificador não puderam ser reconhecidos.'
            );
        }

        return [$token, str_pad((string) ((int) preg_replace('/\D+/', '', $id)), 6, '0', STR_PAD_LEFT)];
    }

    private function urlsDaUf(string $uf, int $ambiente): array
    {
        $overrideQr = trim((string) config('services.sefaz.nfce_qrcode_url'));
        $overrideChave = trim((string) config('services.sefaz.nfce_consulta_url'));
        if ($overrideQr !== '' && $overrideChave !== '') {
            return [$overrideQr, $overrideChave];
        }

        $arquivo = base_path('vendor/nfephp-org/sped-nfe/storage/wsnfe_4.00_mod65.xml');
        if (! is_file($arquivo)) {
            throw new RuntimeException('Tabela de URLs da NFC-e não encontrada no pacote NFePHP.');
        }

        $xml = simplexml_load_file($arquivo);
        if (! $xml) {
            throw new RuntimeException('Não foi possível ler a tabela de URLs da NFC-e do NFePHP.');
        }

        $nos = $xml->xpath("//*[local-name()='UF'][*[local-name()='sigla' and text()='{$uf}']]");
        if (! isset($nos[0])) {
            throw new RuntimeException("Não existem URLs NFC-e cadastradas para a UF {$uf} no NFePHP.");
        }

        $grupo = $ambiente === 1 ? $nos[0]->producao : $nos[0]->homologacao;
        $urlQr = trim((string) ($grupo->NfeConsultaQR ?? ''));
        $urlChave = trim((string) ($grupo->NfeConsulta ?? $grupo->NfeConsultaChave ?? ''));

        // Em alguns arquivos a consulta por chave não está na tabela da UF.
        // Nesse caso, usa explicitamente o override do .env, sem inventar endereço.
        if ($urlQr === '') {
            throw new RuntimeException("URL NfeConsultaQR ausente para {$uf} no ambiente selecionado.");
        }
        if ($urlChave === '') {
            $urlChave = $overrideChave;
        }
        if ($urlChave === '') {
            throw new RuntimeException(
                'URL de consulta por chave ausente. Configure SEFAZ_NFCE_CONSULTA_URL no arquivo .env.'
            );
        }

        return [$urlQr, $urlChave];
    }

    private function primeiro(array $dados, array $campos): string
    {
        foreach ($campos as $campo) {
            if (array_key_exists($campo, $dados) && trim((string) $dados[$campo]) !== '') {
                return trim((string) $dados[$campo]);
            }
        }
        return '';
    }

    private function urlSegura(?string $url): ?string
    {
        $url = html_entity_decode(trim((string) $url), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        return $url;
    }
}
