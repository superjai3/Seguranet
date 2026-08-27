# Publica Seguranet en el servidor, desde Windows.
#
#   .\despliegue\publicar.ps1
#   .\despliegue\publicar.ps1 -Servidor ubuntu@1.2.3.4
#
# Es una envoltura: el trabajo lo hace publicar.sh. Dos programas que despliegan
# terminan siempre igual —uno queda viejo— y el que queda viejo es el que se usa
# el día que hay un apuro.

[CmdletBinding()]
param(
    # La IP se pasa acá hasta que sea definitiva; entonces conviene dejarla fija
    # en publicar.sh y olvidarse.
    [string]$Servidor = $env:SEGURANET_SERVIDOR,
    [string]$Llave = "$HOME\.ssh\seguranet.key"
)

$ErrorActionPreference = 'Stop'

if (-not $Servidor) {
    Write-Host "Falta decir a qué servidor publicar." -ForegroundColor Red
    Write-Host ""
    Write-Host "  .\despliegue\publicar.ps1 -Servidor ubuntu@LA-IP"
    Write-Host ""
    Write-Host "O dejarlo fijo para no repetirlo cada vez:"
    Write-Host '  [Environment]::SetEnvironmentVariable("SEGURANET_SERVIDOR","ubuntu@LA-IP","User")'
    exit 1
}

# Git para Windows trae un bash. Es el mismo que corre el script en Linux, así
# que no hay dos versiones del despliegue que puedan divergir.
$bash = Get-Command bash -ErrorAction SilentlyContinue
if (-not $bash) {
    foreach ($ruta in @("$env:ProgramFiles\Git\bin\bash.exe", "${env:ProgramFiles(x86)}\Git\bin\bash.exe")) {
        if (Test-Path $ruta) { $bash = $ruta; break }
    }
} else {
    $bash = $bash.Source
}

if (-not $bash) {
    Write-Host "No encuentro bash. Hace falta Git para Windows:" -ForegroundColor Red
    Write-Host "  winget install --id Git.Git"
    exit 1
}

if (-not (Test-Path $Llave)) {
    Write-Host "No encuentro la clave SSH en $Llave" -ForegroundColor Red
    Write-Host "Es la que descargaste al crear la instancia en Oracle."
    exit 1
}

$raiz = Split-Path -Parent $PSScriptRoot

# Las rutas de Windows no le sirven a bash: C:\Users\... pasa a /c/Users/...
function ARutaBash([string]$p) {
    $p = (Resolve-Path $p).Path
    return '/' + $p.Substring(0, 1).ToLower() + $p.Substring(2).Replace('\', '/')
}

$env:SERVIDOR = $Servidor
$env:LLAVE = ARutaBash $Llave

& $bash (ARutaBash "$raiz\despliegue\publicar.sh")
exit $LASTEXITCODE
