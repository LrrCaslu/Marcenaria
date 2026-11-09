$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
Write-Host 'Marcenaria Aurora - Instalador ServBay Windows' -ForegroundColor Cyan
$php = $null
foreach ($name in @('php-8.3','php-8.4','php-8.5','php')) {
    $cmd = Get-Command $name -ErrorAction SilentlyContinue
    if ($cmd) { $php = $cmd.Source; break }
}
if (-not $php) {
    foreach ($ver in @('8.3','8.4','8.5')) {
        foreach ($candidate in @("C:\ServBay\package\php\$ver\current\bin\php.exe", "C:\ServBay\package\php\$ver\current\php.exe")) {
            if (Test-Path -LiteralPath $candidate) { $php = $candidate; break }
        }
        if ($php) { break }
    }
}
if (-not $php) {
    Write-Host 'PHP nao encontrado. Ative o PHP 8.3+ no ServBay e habilite a ferramenta de linha de comando.' -ForegroundColor Red
    exit 1
}
Write-Host "PHP detectado: $php"
& $php tools/setup_servbay.php
if ($LASTEXITCODE -ne 0) { Write-Host 'Instalacao nao concluida. Leia a mensagem de erro acima.' -ForegroundColor Yellow; exit $LASTEXITCODE }
Write-Host 'Abra o ServBay > Sites e cadastre o diretorio public como raiz do site.' -ForegroundColor Green
