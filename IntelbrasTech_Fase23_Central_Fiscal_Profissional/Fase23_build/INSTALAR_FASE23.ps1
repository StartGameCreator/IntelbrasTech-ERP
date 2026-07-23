$ErrorActionPreference='Stop'
$root='D:\Skill\IntelbrasTech-ERP'
$backend=Join-Path $root 'backend'; $frontend=Join-Path $root 'frontend'; $payload=Join-Path $PSScriptRoot 'payload'
if(!(Test-Path $backend) -or !(Test-Path $frontend)){throw 'Projeto não encontrado em D:\Skill\IntelbrasTech-ERP'}
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'; $backup=Join-Path $root "patches\Back-Up\fase23-central-fiscal-$stamp"; New-Item -ItemType Directory -Force -Path $backup|Out-Null
$targets=@('backend\routes\api.php','frontend\src\router\index.js','frontend\src\components\layout\AppLayout.vue')
foreach($t in $targets){$src=Join-Path $root $t;if(Test-Path $src){$dst=Join-Path $backup $t;New-Item -ItemType Directory -Force -Path (Split-Path $dst)|Out-Null;Copy-Item $src $dst -Force}}
Copy-Item (Join-Path $payload 'backend\*') $backend -Recurse -Force
Copy-Item (Join-Path $payload 'frontend\*') $frontend -Recurse -Force
$api=Join-Path $backend 'routes\api.php'; $txt=Get-Content $api -Raw
if($txt -notmatch 'CentralFiscalController'){
 $needle='        // Controle da sequência fiscal'
 if($txt -notmatch [regex]::Escape($needle)){$needle='        // Controle da sequ'}
 $block=@"
        // Central Fiscal Profissional - Fase 23
        Route::prefix('fiscal/central')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'dashboard']);
            Route::get('notas', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'index']);
            Route::get('notas/{id}/segunda-via', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'segundaVia']);
            Route::get('notas/{id}/xml', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'xml']);
            Route::post('notas/{id}/consultar-sefaz', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'consultar']);
            Route::get('notas/{id}/historico', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'historico']);
        });

"@
 $idx=$txt.IndexOf($needle); if($idx -lt 0){throw 'Ponto de inserção das rotas não encontrado'};$txt=$txt.Insert($idx,$block);Set-Content $api $txt -Encoding UTF8
}
$router=Join-Path $frontend 'src\router\index.js';$r=Get-Content $router -Raw
if($r -notmatch "fiscal.central"){$needle="        {`n          path: 'fiscal/nfe',";$block="        {`n          path: 'fiscal/central',`n          name: 'fiscal.central',`n          component: () => import('@/views/fiscal/CentralFiscalView.vue'),`n          meta: { title: 'Central Fiscal', permissao: 'fiscal.view' },`n        },`n";$idx=$r.IndexOf($needle);if($idx -lt 0){throw 'Ponto de inserção do router não encontrado'};$r=$r.Insert($idx,$block);Set-Content $router $r -Encoding UTF8}
$menu=Join-Path $frontend 'src\components\layout\AppLayout.vue';$m=Get-Content $menu -Raw
if($m -notmatch "to: '/fiscal/central'"){$needle="      { to: '/fiscal/nfe', label: 'NF-e'";$idx=$m.IndexOf($needle);if($idx -lt 0){throw 'Ponto de inserção do menu não encontrado'};$line="      { to: '/fiscal/central', label: 'Central Fiscal', description: 'Segunda via, XML e consulta SEFAZ', icon: DocumentTextIcon },`r`n";$m=$m.Insert($idx,$line);Set-Content $menu $m -Encoding UTF8}
Push-Location $backend
php artisan migrate --force
php artisan optimize:clear
php artisan route:list --path=fiscal/central
Pop-Location
Push-Location $frontend
npm run build
Pop-Location
Write-Host "FASE 23 instalada com sucesso. Backup: $backup" -ForegroundColor Green
