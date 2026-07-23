param(
    [string]$Projeto = "D:\Skill\IntelbrasTech-ERP"
)

$ErrorActionPreference = 'Stop'
$backend = Join-Path $Projeto 'backend'
$saida = Join-Path $Projeto ('diagnostico-qrcode-nfce-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $saida | Out-Null

$arquivos = @(
    'app\Http\Controllers\Api\V1\Pdv\NfcePdvController.php',
    'app\Services\Fiscal\PdvNfceService.php',
    'app\Services\Fiscal\XmlNFeGenerator.php',
    'app\Services\Fiscal\MotorFiscalService.php',
    'app\Services\Fiscal\NFeService.php',
    'app\Models\NotaFiscal.php',
    'resources\views\pdf\danfe-nfce-pdv.blade.php',
    'config\dompdf.php',
    'composer.json',
    'composer.lock'
)

foreach ($rel in $arquivos) {
    $origem = Join-Path $backend $rel
    if (Test-Path $origem) {
        $destino = Join-Path $saida $rel
        New-Item -ItemType Directory -Force -Path (Split-Path $destino -Parent) | Out-Null
        Copy-Item $origem $destino -Force
    }
}

$relatorio = Join-Path $saida 'RELATORIO_QRCODE.txt'
"DIAGNOSTICO QRCODE NFC-e" | Set-Content $relatorio -Encoding UTF8
"Gerado em: $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')" | Add-Content $relatorio -Encoding UTF8
"Projeto: $Projeto" | Add-Content $relatorio -Encoding UTF8
"" | Add-Content $relatorio

Push-Location $backend
try {
    "=== PHP / EXTENSOES ===" | Add-Content $relatorio
    (& php -r "echo PHP_BINARY.PHP_EOL; echo php_ini_loaded_file().PHP_EOL; echo 'gd='.(extension_loaded('gd')?'sim':'nao').PHP_EOL; echo 'dom='.(extension_loaded('dom')?'sim':'nao').PHP_EOL; echo 'simplexml='.(extension_loaded('simplexml')?'sim':'nao').PHP_EOL;") 2>&1 | Add-Content $relatorio

    "`n=== PACOTES DE QRCODE / DANFE ===" | Add-Content $relatorio
    (& composer show 2>&1 | Select-String -Pattern 'qrcode|barcode|nfephp|sped|dompdf') | Add-Content $relatorio

    "`n=== CAMPOS DA ULTIMA NOTA NFC-e ===" | Add-Content $relatorio
    $php = @'
$nota = App\Models\NotaFiscal::query()->where('modelo','65')->latest('id')->first();
if (!$nota) { echo "SEM_NFCE\n"; return; }
echo json_encode([
'id'=>$nota->id,
'status'=>$nota->status,
'ambiente'=>$nota->ambiente,
'chave'=>$nota->chave_acesso,
'protocolo'=>$nota->protocolo,
'xml_len'=>strlen((string)$nota->xml),
'xml_assinado_len'=>strlen((string)($nota->xml_assinado ?? '')),
'xml_autorizado_len'=>strlen((string)($nota->xml_autorizado ?? '')),
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), "\n";
$xml = (string)($nota->xml_autorizado ?: $nota->xml_assinado ?: $nota->xml);
if ($xml !== '') {
  $dom = new DOMDocument();
  if (@$dom->loadXML($xml)) {
    $xp = new DOMXPath($dom);
    $q = $xp->query("//*[local-name()='infNFeSupl']/*[local-name()='qrCode']")->item(0);
    $u = $xp->query("//*[local-name()='infNFeSupl']/*[local-name()='urlChave']")->item(0);
    echo "QRCODE_URL=".($q ? trim($q->textContent) : 'AUSENTE')."\n";
    echo "URL_CHAVE=".($u ? trim($u->textContent) : 'AUSENTE')."\n";
  } else { echo "XML_INVALIDO\n"; }
}
'@
    (& php artisan tinker --execute=$php) 2>&1 | Add-Content $relatorio

    "`n=== OCORRENCIAS NO CODIGO ===" | Add-Content $relatorio
    Get-ChildItem app,resources -Recurse -File -ErrorAction SilentlyContinue |
      Select-String -Pattern 'infNFeSupl|qrCode|urlChave|QRCode|QrCode|nfe.fazenda.gov.br|sefaz' -CaseSensitive:$false |
      ForEach-Object { "$($_.Path):$($_.LineNumber): $($_.Line.Trim())" } |
      Add-Content $relatorio
}
finally {
    Pop-Location
}

$zip = "$saida.zip"
Compress-Archive -Path (Join-Path $saida '*') -DestinationPath $zip -Force
Write-Host "" 
Write-Host "Diagnostico criado:" -ForegroundColor Green
Write-Host $zip -ForegroundColor Cyan
Write-Host "Envie esse ZIP para analise." -ForegroundColor Yellow
