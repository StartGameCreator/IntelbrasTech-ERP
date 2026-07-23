param([string]$Projeto = "D:\Skill\IntelbrasTech-ERP")
$ErrorActionPreference = 'Stop'
$backend = Join-Path $Projeto 'backend'
$payload = Join-Path $PSScriptRoot 'payload'
if (-not (Test-Path (Join-Path $backend 'artisan'))) { throw "Backend Laravel não encontrado em $backend" }
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $Projeto "patches\Back-Up\Fase22_2_QRCode_SEFAZ_$stamp"
New-Item -ItemType Directory -Force -Path $backup | Out-Null
$files = @(
 'app\Services\Fiscal\NFeService.php',
 'app\Services\Fiscal\DanfeQrCodeService.php',
 'app\Http\Controllers\Api\V1\Pdv\NfcePdvController.php',
 'resources\views\pdf\danfe-nfce-pdv.blade.php'
)
foreach($rel in $files){ $src=Join-Path $backend $rel; if(Test-Path $src){$dst=Join-Path $backup $rel; New-Item -ItemType Directory -Force -Path (Split-Path $dst) | Out-Null; Copy-Item $src $dst -Force}}
Copy-Item (Join-Path $payload 'app\Services\Fiscal\NfceXmlSupplementService.php') (Join-Path $backend 'app\Services\Fiscal\NfceXmlSupplementService.php') -Force
Copy-Item (Join-Path $payload 'app\Services\Fiscal\DanfeQrCodeService.php') (Join-Path $backend 'app\Services\Fiscal\DanfeQrCodeService.php') -Force
$php = (Get-Command php -ErrorAction Stop).Source
& $php (Join-Path $payload 'scripts\patch.php') $backend
if($LASTEXITCODE -ne 0){ throw 'Falha ao aplicar alterações estruturais.' }
Push-Location $backend
try {
  & $php artisan optimize:clear
  & $php -l 'app\Services\Fiscal\NfceXmlSupplementService.php'
  & $php -l 'app\Services\Fiscal\DanfeQrCodeService.php'
  & $php -l 'app\Services\Fiscal\NFeService.php'
  & $php -l 'app\Http\Controllers\Api\V1\Pdv\NfcePdvController.php'
  if($LASTEXITCODE -ne 0){ throw 'Validação PHP falhou.' }
} finally { Pop-Location }
Write-Host "`nPatch instalado com sucesso." -ForegroundColor Green
Write-Host "Backup: $backup"
Write-Host "Emita uma NOVA NFC-e de homologação e teste o QR Code pelo celular." -ForegroundColor Yellow
Pause
