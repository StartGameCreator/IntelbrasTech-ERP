param(
    [string]$Projeto = "D:\Skill\IntelbrasTech-ERP"
)

$ErrorActionPreference = "Stop"
$PatchDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$Backend = Join-Path $Projeto "backend"
$Frontend = Join-Path $Projeto "frontend"
$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$Backup = Join-Path $Projeto "patches\Back-Up\Fase23_1-$Stamp"
$LogDir = Join-Path $Projeto "patches\Logs"
$Log = Join-Path $LogDir "fase23_1-$Stamp.log"

New-Item -ItemType Directory -Force -Path $Backup, $LogDir | Out-Null

function Log([string]$Texto) {
    $linha = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Texto"
    Write-Host $linha
    Add-Content -Path $Log -Value $linha -Encoding UTF8
}

function Backup-File([string]$Arquivo, [string]$Relativo) {
    if (Test-Path $Arquivo) {
        $Destino = Join-Path $Backup $Relativo
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Destino) | Out-Null
        Copy-Item $Arquivo $Destino -Force
    }
}

try {
    Log "Iniciando Fase 23.1 - QR Code Endroid 6.x e Segunda Via."

    if (!(Test-Path (Join-Path $Backend "artisan"))) {
        throw "Backend não encontrado em $Backend"
    }
    if (!(Test-Path (Join-Path $Frontend "package.json"))) {
        throw "Frontend não encontrado em $Frontend"
    }

    $Arquivos = @(
        @{ Origem = "payload\backend\app\Services\Fiscal\DanfeQrCodeService.php"; Destino = "backend\app\Services\Fiscal\DanfeQrCodeService.php" },
        @{ Origem = "payload\backend\app\Http\Controllers\Api\V1\Pdv\NfcePdvController.php"; Destino = "backend\app\Http\Controllers\Api\V1\Pdv\NfcePdvController.php" },
        @{ Origem = "payload\frontend\src\views\pdv\PdvFiscalView.vue"; Destino = "frontend\src\views\pdv\PdvFiscalView.vue" }
    )

    foreach ($Item in $Arquivos) {
        $DestinoAbs = Join-Path $Projeto $Item.Destino
        Backup-File $DestinoAbs $Item.Destino

        $OrigemAbs = Join-Path $PatchDir $Item.Origem
        if (!(Test-Path $OrigemAbs)) {
            throw "Payload ausente: $OrigemAbs"
        }

        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $DestinoAbs) | Out-Null
        Copy-Item $OrigemAbs $DestinoAbs -Force
        Log "Atualizado: $($Item.Destino)"
    }

    Push-Location $Backend
    try {
        php -l "app\Services\Fiscal\DanfeQrCodeService.php" | Tee-Object -FilePath $Log -Append
        if ($LASTEXITCODE -ne 0) { throw "Erro de sintaxe no DanfeQrCodeService.php" }

        php -l "app\Http\Controllers\Api\V1\Pdv\NfcePdvController.php" | Tee-Object -FilePath $Log -Append
        if ($LASTEXITCODE -ne 0) { throw "Erro de sintaxe no NfcePdvController.php" }

        php artisan optimize:clear | Tee-Object -FilePath $Log -Append
        if ($LASTEXITCODE -ne 0) { throw "Falha ao limpar cache do Laravel" }
    }
    finally {
        Pop-Location
    }

    Push-Location $Frontend
    try {
        npm run build | Tee-Object -FilePath $Log -Append
        if ($LASTEXITCODE -ne 0) { throw "Falha no build do frontend" }
    }
    finally {
        Pop-Location
    }

    Log "Instalação concluída com sucesso."
    Log "Backup: $Backup"
    Write-Host ""
    Write-Host "CORREÇÃO INSTALADA COM SUCESSO." -ForegroundColor Green
    Write-Host "Teste: NFC-e do PDV > Baixar DANFE e Gerar segunda via." -ForegroundColor Cyan
}
catch {
    Log "ERRO: $($_.Exception.Message)"
    Write-Host ""
    Write-Host "A instalação falhou. Consulte: $Log" -ForegroundColor Red
    Write-Host "O backup está em: $Backup" -ForegroundColor Yellow
    exit 1
}
