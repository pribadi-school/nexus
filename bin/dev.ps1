<#
  Runs Nexus on this computer: http://localhost:8000

  Uses the PHP and MariaDB that come with LocalWP, so nothing else needs
  installing. The first run creates a local database in .dev\mariadb, a local
  .env, imports the migrations and loads the sample data.

    powershell -ExecutionPolicy Bypass -File bin\dev.ps1           start
    powershell -ExecutionPolicy Bypass -File bin\dev.ps1 -Reset    wipe the local database and start fresh
    powershell -ExecutionPolicy Bypass -File bin\dev.ps1 -Stop     stop the local database

  LocalWP somewhere else? Set NEXUS_LOCALWP to its folder first.
#>
param(
  [switch]$Reset,
  [switch]$Stop,
  [int]$Port = 8000,
  [int]$DbPort = 3307
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
$LocalWP = if ($env:NEXUS_LOCALWP) { $env:NEXUS_LOCALWP } else { 'E:\LocalWP' }
$Services = Join-Path $LocalWP 'resources\extraResources\lightning-services'

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

$PhpArgs = @(
  '-n',
  '-d', "extension_dir=$PhpDir\ext",
  '-d', 'extension=pdo_mysql',
  '-d', 'extension=mbstring',
  '-d', 'extension=openssl',
  '-d', 'extension=fileinfo',
  '-d', 'memory_limit=256M',
  '-d', 'upload_max_filesize=10M',
  '-d', 'post_max_size=12M',
  '-d', 'date.timezone=UTC'
)

& $Php @PhpArgs (Join-Path $Root 'bin\migrate.php') --seed
if ($LASTEXITCODE -ne 0) { throw 'Migrations failed.' }

# 4. Web server -----------------------------------------------------------------
"Nexus is running at http://localhost:$Port  (Ctrl+C to stop; the database keeps running, stop it with -Stop)"
& $Php @PhpArgs -S "127.0.0.1:$Port" -t (Join-Path $Root 'public') (Join-Path $Root 'public\index.php')
