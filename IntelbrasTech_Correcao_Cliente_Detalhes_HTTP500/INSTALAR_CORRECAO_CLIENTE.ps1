$ErrorActionPreference = 'Stop'
$ProjectRoot = 'D:\Skill\IntelbrasTech-ERP'
$Backend = Join-Path $ProjectRoot 'backend'
$Relative = 'app\Http\Controllers\Api\V1\Cadastros\ClienteController.php'
$Target = Join-Path $Backend $Relative
$Source = Join-Path $PSScriptRoot ('payload\backend\' + $Relative)
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$BackupRoot = Join-Path $ProjectRoot ('patches\Back-Up\Correcao_Cliente_Detalhes_' + $Stamp)
$Backup = Join-Path $BackupRoot $Relative
$LogDir = Join-Path $ProjectRoot 'patches\Logs'
$Log = Join-Path $LogDir ('correcao-cliente-' + $Stamp + '.log')

New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
function Write-Log([string]$Text) {
    $line = ('[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Text)
    Write-Host $line
    Add-Content -Path $Log -Value $line -Encoding UTF8
}

try {
    Write-Log 'Iniciando correção da tela de detalhes do cliente.'
    if (-not (Test-Path $Backend)) { throw "Backend não encontrado: $Backend" }
    if (-not (Test-Path $Target)) { throw "Controller não encontrado: $Target" }
    if (-not (Test-Path $Source)) { throw "Payload não encontrado: $Source" }

    New-Item -ItemType Directory -Force -Path (Split-Path $Backup -Parent) | Out-Null
    Copy-Item $Target $Backup -Force
    Write-Log "Backup criado em: $BackupRoot"

    Copy-Item $Source $Target -Force
    Write-Log 'Controller corrigido instalado.'

    Push-Location $Backend
    try {
        & php -l $Target | Tee-Object -FilePath $Log -Append
        if ($LASTEXITCODE -ne 0) { throw 'Falha na validação de sintaxe PHP.' }

        & php artisan optimize:clear | Tee-Object -FilePath $Log -Append
        if ($LASTEXITCODE -ne 0) { throw 'Falha ao limpar os caches Laravel.' }
    }
    finally { Pop-Location }

    Write-Log 'Correção instalada com sucesso.'
    Write-Host ''
    Write-Host 'Teste agora: Clientes > abrir qualquer cliente.' -ForegroundColor Green
    exit 0
}
catch {
    Write-Log ('ERRO: ' + $_.Exception.Message)
    if (Test-Path $Backup) {
        Copy-Item $Backup $Target -Force
        Write-Log 'Backup restaurado automaticamente.'
    }
    exit 1
}
