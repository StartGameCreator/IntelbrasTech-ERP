<?php
$root = $argv[1] ?? null;
if (!$root || !is_dir($root)) { fwrite(STDERR, "Raiz do backend inválida.\n"); exit(2); }

function patchFile(string $file, callable $fn): void {
    if (!is_file($file)) throw new RuntimeException("Arquivo não encontrado: {$file}");
    $original = file_get_contents($file);
    $novo = $fn($original);
    if ($novo === $original) throw new RuntimeException("Nenhuma alteração aplicada em: {$file}");
    file_put_contents($file, $novo);
}

$nfeService = $root.'/app/Services/Fiscal/NFeService.php';
patchFile($nfeService, function(string $s): string {
    if (str_contains($s, 'NfceXmlSupplementService')) return $s."\n";
    $needle = "            // 3. Enviar ao SEFAZ\n";
    $insert = "            // 2.1. NFC-e: inclui infNFeSupl com QR Code oficial antes do envio\n"
        ."            if ((string) \$nfe->modelo === '65') {\n"
        ."                \$xmlAssinado = app(NfceXmlSupplementService::class)->aplicar(\$nfe, \$xmlAssinado);\n"
        ."            }\n\n";
    if (!str_contains($s, $needle)) throw new RuntimeException('Marcador de envio SEFAZ não encontrado no NFeService.');
    $s = str_replace($needle, $insert.$needle, $s);

    $needle2 = "                // Autorizada\n                \$nfe->update([";
    $insert2 = "                // Autorizada\n                \$suplementoNfce = app(NfceXmlSupplementService::class)->extrair(\n"
        ."                    \$retorno['xml'] ?? \$this->montarXmlProcessado(\$xmlAssinado, \$retorno)\n"
        ."                );\n                \$nfe->update([";
    if (!str_contains($s, $needle2)) throw new RuntimeException('Marcador de autorização não encontrado no NFeService.');
    $s = str_replace($needle2, $insert2, $s);

    $needle3 = "                    'data_autorizacao' => now(),\n";
    $insert3 = "                    'data_autorizacao' => now(),\n"
        ."                    'qr_code_url'     => \$suplementoNfce['qr_code_url'],\n"
        ."                    'url_chave'       => \$suplementoNfce['url_chave'],\n";
    if (!str_contains($s, $needle3)) throw new RuntimeException('Campo data_autorizacao não encontrado no NFeService.');
    $s = str_replace($needle3, $insert3, $s, $count);
    if (! $count) throw new RuntimeException('Não foi possível inserir os campos do QR Code.');
    return $s;
});

$controller = $root.'/app/Http/Controllers/Api/V1/Pdv/NfcePdvController.php';
patchFile($controller, function(string $s): string {
    if (str_contains($s, '$danfeQrCodeUrl')) return $s."\n";
    $needle = "        \$danfeQrCodeDataUri = \$this->danfeQrCode->dataUri(\$venda->notaFiscal);";
    $replace = "        \$danfeQrCode = \$this->danfeQrCode->metadata(\$venda->notaFiscal);\n"
        ."        \$danfeQrCodeDataUri = \$danfeQrCode['data_uri'];\n"
        ."        \$danfeQrCodeUrl = \$danfeQrCode['qr_code_url'];\n"
        ."        \$danfeConsultaUrl = \$danfeQrCode['consulta_url'];";
    if (!str_contains($s, $needle)) throw new RuntimeException('Chamada do DanfeQrCodeService não encontrada.');
    $s = str_replace($needle, $replace, $s);
    $needle2 = "compact('venda', 'documento', 'empresa', 'danfeLogoDataUri', 'danfeQrCodeDataUri', 'danfeBarcodeDataUri', 'danfeTributos', 'danfeDados')";
    $replace2 = "compact('venda', 'documento', 'empresa', 'danfeLogoDataUri', 'danfeQrCodeDataUri', 'danfeQrCodeUrl', 'danfeConsultaUrl', 'danfeBarcodeDataUri', 'danfeTributos', 'danfeDados')";
    if (!str_contains($s, $needle2)) throw new RuntimeException('compact() do DANFE não encontrado.');
    return str_replace($needle2, $replace2, $s);
});

$view = $root.'/resources/views/pdf/danfe-nfce-pdv.blade.php';
patchFile($view, function(string $s): string {
    if (str_contains($s, 'Consulta NFC-e pelo QR Code oficial')) return $s."\n";
    $old = "Consulta de autenticidade no portal nacional da NF-e<br>\nwww.nfe.fazenda.gov.br/portal<br>\nou no site da Sefaz autorizadora";
    $new = "@if(!empty(\$danfeConsultaUrl))\nConsulta da NFC-e no endereço oficial da SEFAZ:<br>\n{{ \$danfeConsultaUrl }}\n@else\nConsulta da NFC-e pelo QR Code oficial da SEFAZ\n@endif";
    if (!str_contains($s, $old)) throw new RuntimeException('Texto antigo da consulta no DANFE não encontrado.');
    $s = str_replace($old, $new, $s);
    $old2 = "@if(\$danfeQrCodeDataUri)<img class=\"qr\" src=\"{{ \$danfeQrCodeDataUri }}\">@else<div class=\"value\" style=\"margin-top:35px\">Indisponível</div>@endif";
    $new2 = "@if(\$danfeQrCodeDataUri)<img class=\"qr\" src=\"{{ \$danfeQrCodeDataUri }}\" alt=\"QR Code oficial da NFC-e\"><div class=\"small\">Consulte a autenticidade da NFC-e</div>@else<div class=\"value\" style=\"margin-top:25px\">QR Code oficial indisponível no XML</div>@endif";
    if (!str_contains($s, $old2)) throw new RuntimeException('Bloco do QR Code não encontrado na view.');
    return str_replace($old2, $new2, $s);
});

echo "PATCH_OK\n";
