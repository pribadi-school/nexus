<#
  Runs Nexus on this computer: http://localhost:8000

  Uses the PHP and MariaDB that come with LocalWP, so nothing else needs
  installing. The first run creates a local database in .dev\mariadb, a local
  .env, imports the migrations and loads the sample data.

    powershell -ExecutionPolicy Bypass -File bin\dev.ps1           start
    powershell -ExecutionPolicy Bypass -File bin\dev.ps1 -Reset    wipe the local database and start fresh
    powershell -ExecutionPolicy Bypass -File bin\dev.ps1 -Stop     stop the local database

  LocalWP is looked for in E:\LocalWP, C:\Program Files (x86)\Local,
  C:\Program Files\Local and %APPDATA%\Local. Somewhere else? Set
  NEXUS_LOCALWP to its folder first.
#>
param(
  [switch]$Reset,
  [switch]$Stop,
  [int]$Port = 8000,
  [int]$DbPort = 3307
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$ServicesPath = 'resources\extraResources\lightning-services'
$LocalWP = if ($env:NEXUS_LOCALWP) { $env:NEXUS_LOCALWP } else {
  $candidates = @('E:\LocalWP', "${env:ProgramFiles(x86)}\Local", "$env:ProgramFiles\Local", "$env:APPDATA\Local")
  $found = $candidates | Where-Object { $_ -and (Test-Path (Join-Path $_ $ServicesPath)) } | Select-Object -First 1
  if (-not $found) { throw "Could not find LocalWP in: $($candidates -join ', '). Set NEXUS_LOCALWP to its folder." }
  $found
}
$Services = Join-Path $LocalWP $ServicesPath

function Find-Service([string]$pattern, [string]$relative) {
  $dir = Get-ChildItem -Path $Services -Directory -Filter $pattern -ErrorAction SilentlyContinue |
    Sort-Object Name -Descending | Select-Object -First 1
  if (-not $dir) { throw "Could not find $pattern in $Services. Is LocalWP installed? Set NEXUS_LOCALWP to its folder." }
  return Join-Path $dir.FullName $relative
}

$PhpDir = Find-Service 'php-8.*' 'bin\win64'
$Php = Join-Path $PhpDir 'php.exe'
$DbBin = Find-Service 'mariadb-*' 'bin\win32\bin'
$DataDir = Join-Path $Root '.dev\mariadb'

function Test-Port([int]$p) {
  $client = New-Object System.Net.Sockets.TcpClient
  try { $client.Connect('127.0.0.1', $p); return $true } catch { return $false } finally { $client.Close() }
}

function Invoke-Sql([string]$sql) {
  & (Join-Path $DbBin 'mariadb.exe') -h 127.0.0.1 -P $DbPort -u root -e $sql
  if ($LASTEXITCODE -ne 0) { throw "SQL failed: $sql" }
}

if ($Stop) {
  if (Test-Port $DbPort) { & (Join-Path $DbBin 'mariadb-admin.exe') -h 127.0.0.1 -P $DbPort -u root shutdown; 'Local database stopped.' }
  else { 'Local database is not running.' }
  exit 0
}

# 1. Local database server -------------------------------------------------
if (-not (Test-Path (Join-Path $DataDir 'my.ini'))) {
  'Creating the local database in .dev\mariadb ...'
  New-Item -ItemType Directory -Force (Split-Path $DataDir) | Out-Null
  & (Join-Path $DbBin 'mariadb-install-db.exe') "--datadir=$DataDir" "--port=$DbPort" --silent | Out-Null
}
if (-not (Test-Port $DbPort)) {
  'Starting the local database on port ' + $DbPort + ' ...'
  Start-Process -FilePath (Join-Path $DbBin 'mariadbd.exe') -WindowStyle Hidden -ArgumentList @(
    "`"--defaults-file=$DataDir\my.ini`"", '--bind-address=127.0.0.1',
    '--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci'
  )
  for ($i = 0; $i -lt 30 -and -not (Test-Port $DbPort); $i++) { Start-Sleep -Milliseconds 500 }
  if (-not (Test-Port $DbPort)) { throw 'The local database did not start. See .dev\mariadb\*.err' }
}

# 2. .env for local development -----------------------------------------------
$EnvFile = Join-Path $Root '.env'
if (-not (Test-Path $EnvFile)) {
  'Creating a local .env ...'
  @"
# Local development settings (created by bin\dev.ps1). Never committed.
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:$Port
APP_TIMEZONE=Asia/Jakarta
SESSION_LIFETIME_DAYS=14

# Local sign-in password (only for this computer)
APP_PASSWORD=nexus-local
LOGIN_MAX_ATTEMPTS=8
LOGIN_LOCKOUT_MINUTES=15

DB_HOST=127.0.0.1
DB_PORT=$DbPort
DB_DATABASE=nexus
DB_USERNAME=nexus
DB_PASSWORD=nexus-local
"@ | Set-Content -Path $EnvFile -Encoding ascii
}

# 3. Database, user, migrations, sample data -------------------------------------
if ($Reset) {
  'Wiping the local database ...'
  Invoke-Sql 'DROP DATABASE IF EXISTS nexus;'
}
Invoke-Sql @"
CREATE DATABASE IF NOT EXISTS nexus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'nexus'@'localhost' IDENTIFIED BY 'nexus-local';
CREATE USER IF NOT EXISTS 'nexus'@'127.0.0.1' IDENTIFIED BY 'nexus-local';
GRANT ALL ON nexus.* TO 'nexus'@'localhost';
GRANT ALL ON nexus.* TO 'nexus'@'127.0.0.1';
"@

# PHP settings go in a generated php.ini rather than -d flags: an unquoted
# extension_dir with "(x86)" in it is an INI syntax error, and Windows
# PowerShell 5.1 mangles embedded quotes in native arguments.
$PhpIni = Join-Path $Root '.dev\php.ini'
@"
; Created by bin\dev.ps1 on every run. Do not edit.
extension_dir="$PhpDir\ext"
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=zip
memory_limit=256M
upload_max_filesize=500M
post_max_size=512M
max_execution_time=300
max_input_time=300
date.timezone=UTC
"@ | Set-Content -Path $PhpIni -Encoding ascii
$PhpArgs = @('-c', $PhpIni)

& $Php @PhpArgs (Join-Path $Root 'bin\migrate.php') --seed
if ($LASTEXITCODE -ne 0) { throw 'Migrations failed.' }

# 4. Web server -----------------------------------------------------------------
"Nexus is running at http://localhost:$Port  (Ctrl+C to stop; the database keeps running, stop it with -Stop)"
& $Php @PhpArgs -S "127.0.0.1:$Port" -t (Join-Path $Root 'public') (Join-Path $Root 'public\index.php')
