param([int]$Port = 17473)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Security

$appDir = Join-Path $env:LOCALAPPDATA 'IntelbrasTech\Assinador'
$tokenFile = Join-Path $appDir 'token.txt'
New-Item -ItemType Directory -Force -Path $appDir | Out-Null
if (!(Test-Path $tokenFile)) {
    $bytes = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    [Convert]::ToBase64String($bytes) | Set-Content -Encoding ASCII -NoNewline $tokenFile
}
$pairingToken = (Get-Content $tokenFile -Raw).Trim()

function Write-JsonResponse($Context, [int]$Status, $Data, [string]$Origin = '') {
    $json = $Data | ConvertTo-Json -Depth 12 -Compress
    $buffer = [Text.Encoding]::UTF8.GetBytes($json)
    $Context.Response.StatusCode = $Status
    $Context.Response.ContentType = 'application/json; charset=utf-8'
    if ($Origin) { $Context.Response.Headers['Access-Control-Allow-Origin'] = $Origin }
    $Context.Response.Headers['Access-Control-Allow-Headers'] = 'Content-Type, X-IntelbrasTech-Token'
    $Context.Response.Headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS'
    $Context.Response.Headers['Cache-Control'] = 'no-store'
    $Context.Response.ContentLength64 = $buffer.Length
    $Context.Response.OutputStream.Write($buffer, 0, $buffer.Length)
    $Context.Response.Close()
}

function Get-AllowedOrigin($Request) {
    $origin = [string]$Request.Headers['Origin']
    if (!$origin) { return '' }
    if ($origin -match '^https?://(127\.0\.0\.1|localhost)(:\d+)?$') { return $origin }
    if ($origin -match '^https://([a-z0-9-]+\.)*intelbrastech\.com\.br$') { return $origin }
    return $null
}

function Get-LocalCertificates {
    Get-ChildItem Cert:\CurrentUser\My | Where-Object { $_.HasPrivateKey } | ForEach-Object {
        $doc = if ($_.Subject -match '(\d{14}|\d{11})') { $Matches[1] } else { $null }
        [pscustomobject]@{ thumbprint=$_.Thumbprint; titular=$_.GetNameInfo('SimpleName',$false); documento=$doc; emissor=$_.Issuer; serie=$_.SerialNumber; validade_inicio=$_.NotBefore.ToString('yyyy-MM-dd'); validade=$_.NotAfter.ToString('yyyy-MM-dd'); valido=$_.NotAfter -gt (Get-Date); possui_chave_privada=$_.HasPrivateKey }
    }
}

function Sign-NFeXml([string]$Xml, [string]$Thumbprint) {
    $cert = Get-Item "Cert:\CurrentUser\My\$Thumbprint" -ErrorAction Stop
    if (!$cert.HasPrivateKey) { throw 'O certificado não possui chave privada acessível.' }
    if ($cert.NotAfter -lt (Get-Date)) { throw 'O certificado selecionado está vencido.' }
    $doc = New-Object Xml.XmlDocument
    $doc.PreserveWhitespace = $true
    $doc.LoadXml($Xml)
    $inf = $doc.SelectSingleNode("//*[local-name()='infNFe']")
    if (!$inf -or !$inf.Id) { throw 'XML NF-e inválido: elemento infNFe/Id não encontrado.' }
    $signed = New-Object Security.Cryptography.Xml.SignedXml($doc)
    try {
        $rsa = [System.Security.Cryptography.X509Certificates.RSACertificateExtensions]::GetRSAPrivateKey($cert)
    }
    catch {
        $rsa = $cert.PrivateKey
    }

    if ($null -eq $rsa) {
        throw 'Nao foi possivel acessar a chave privada RSA do certificado selecionado.'
    }

    $signed.SigningKey = $rsa
    $signed.SignedInfo.CanonicalizationMethod = [Security.Cryptography.Xml.SignedXml]::XmlDsigC14NTransformUrl
    $signed.SignedInfo.SignatureMethod = [Security.Cryptography.Xml.SignedXml]::XmlDsigRSASHA1Url
    $reference = New-Object Security.Cryptography.Xml.Reference
    $reference.Uri = "#$($inf.Id)"
    $reference.DigestMethod = [Security.Cryptography.Xml.SignedXml]::XmlDsigSHA1Url
    $reference.AddTransform((New-Object Security.Cryptography.Xml.XmlDsigEnvelopedSignatureTransform))
    $reference.AddTransform((New-Object Security.Cryptography.Xml.XmlDsigC14NTransform))
    $signed.AddReference($reference)
    $keyInfo = New-Object Security.Cryptography.Xml.KeyInfo
    $keyInfo.AddClause((New-Object Security.Cryptography.Xml.KeyInfoX509Data($cert)))
    $signed.KeyInfo = $keyInfo
    $signed.ComputeSignature()
    $signature = $doc.ImportNode($signed.GetXml(), $true)
    [void]$inf.ParentNode.AppendChild($signature)
    return $doc.OuterXml
}

$listener = New-Object Net.HttpListener
$listener.Prefixes.Add("http://127.0.0.1:$Port/")
$listener.Start()
Write-Host ''
Write-Host 'IntelbrasTech Assinador Local (EXPERIMENTAL)' -ForegroundColor Green
Write-Host "Endereço: http://127.0.0.1:$Port"
Write-Host "Código de pareamento: $pairingToken" -ForegroundColor Yellow
Write-Host 'A chave privada permanece no repositório do Windows.'
Write-Host 'Pressione Ctrl+C para encerrar.'

try {
    while ($listener.IsListening) {
        $ctx = $listener.GetContext(); $req = $ctx.Request
        $origin = Get-AllowedOrigin $req
        if ($null -eq $origin) { Write-JsonResponse $ctx 403 @{message='Origem não autorizada.'}; continue }
        if ($req.HttpMethod -eq 'OPTIONS') { Write-JsonResponse $ctx 200 @{ok=$true} $origin; continue }
        if ($req.Url.AbsolutePath -eq '/health') { Write-JsonResponse $ctx 200 @{service='IntelbrasTech Assinador';version='0.1.0';experimental=$true;platform='Windows';requires_pairing=$true} $origin; continue }
        if ([string]$req.Headers['X-IntelbrasTech-Token'] -ne $pairingToken) { Write-JsonResponse $ctx 401 @{message='Código de pareamento inválido.'} $origin; continue }
        if ($req.HttpMethod -eq 'GET' -and $req.Url.AbsolutePath -eq '/certificates') { Write-JsonResponse $ctx 200 @{certificates=@(Get-LocalCertificates)} $origin; continue }
        if ($req.HttpMethod -eq 'POST' -and $req.Url.AbsolutePath -eq '/sign') {
            try { $reader=New-Object IO.StreamReader($req.InputStream,$req.ContentEncoding);$body=$reader.ReadToEnd()|ConvertFrom-Json;$xml=Sign-NFeXml $body.xml $body.thumbprint;Write-JsonResponse $ctx 200 @{signed_xml=$xml;thumbprint=$body.thumbprint;signed_at=(Get-Date).ToString('o')} $origin } catch { Write-JsonResponse $ctx 422 @{message=$_.Exception.Message} $origin }; continue
        }
        Write-JsonResponse $ctx 404 @{message='Rota não encontrada.'} $origin
    }
} finally { $listener.Stop(); $listener.Close() }
