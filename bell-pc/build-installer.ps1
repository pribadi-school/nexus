# Builds bell-pc\installer\Output\NexusBell-Setup-<version>.exe with Inno Setup 6
# (https://jrsoftware.org/isinfo.php, or: winget install JRSoftware.InnoSetup).
#
#   powershell -ExecutionPolicy Bypass -File build-installer.ps1 [-Exe path\to\NexusBell.exe]
#
# The installer wraps NexusBell.exe: run build.ps1 first to make dist\NexusBell.exe,
# or pass -Exe (for example ..\releases\NexusBell.exe). The version is read from
# nexusbell\__init__.py so the installer and the app always match.
param([string]$Exe = '')
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$iscc = @(
    "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe",
    "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
    "$env:ProgramFiles\Inno Setup 6\ISCC.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $iscc) { throw 'Inno Setup 6 was not found. Install it with: winget install JRSoftware.InnoSetup' }

if (-not $Exe) {
    $Exe = if (Test-Path 'dist\NexusBell.exe') { 'dist\NexusBell.exe' } else { '..\releases\NexusBell.exe' }
}
$Exe = (Resolve-Path $Exe).Path

$version = (Select-String -Path 'nexusbell\__init__.py' -Pattern 'VERSION\s*=\s*"([^"]+)"').Matches[0].Groups[1].Value
Write-Host "Packaging $Exe as version $version"

& $iscc "/DAppVersion=$version" "/DExeSource=$Exe" 'installer\NexusBell.iss'
if ($LASTEXITCODE -ne 0) { throw 'Inno Setup failed.' }

$setup = "installer\Output\NexusBell-Setup-$version.exe"
$hash = (Get-FileHash $setup -Algorithm SHA256).Hash.ToLower()
Write-Host "Done: $setup"
Write-Host "SHA-256: $hash"
