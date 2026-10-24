<#
  Build and check the Nexus Android TV player, then copy the APK to dist/.

    powershell -ExecutionPolicy Bypass -File bin\build-android.ps1
    powershell -ExecutionPolicy Bypass -File bin\build-android.ps1 -LandscapeWifi
    powershell -ExecutionPolicy Bypass -File bin\build-android.ps1 -JdkRoot C:\Java\jdk-17 -SdkRoot C:\Android\Sdk

  Requires JDK 17, Android SDK platform 36, Build Tools 35.0.0, and Platform
  Tools. Gradle and Maven dependencies download on the first build. This
  script does not install tools, accept SDK licenses, or modify the web DB.
#>
param(
  [string]$JdkRoot = '',
  [string]$SdkRoot = '',
  [switch]$LandscapeWifi
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$androidProject = Join-Path $projectRoot 'android-tv'
$toolsRoot = Join-Path $projectRoot '.dev\android-tools'

if (-not $JdkRoot) {
  if ($env:JAVA_HOME -and (Test-Path -LiteralPath (Join-Path $env:JAVA_HOME 'bin\javac.exe'))) {
    $JdkRoot = $env:JAVA_HOME
  } else {
    $bundledJdk = Get-ChildItem -LiteralPath (Join-Path $toolsRoot 'jdk') -Directory -ErrorAction SilentlyContinue |
      Where-Object { Test-Path -LiteralPath (Join-Path $_.FullName 'bin\javac.exe') } |
      Sort-Object Name -Descending | Select-Object -First 1
    if ($bundledJdk) { $JdkRoot = $bundledJdk.FullName }
  }
}
if (-not $SdkRoot) {
  if ($env:ANDROID_HOME) { $SdkRoot = $env:ANDROID_HOME }
  elseif ($env:ANDROID_SDK_ROOT) { $SdkRoot = $env:ANDROID_SDK_ROOT }
  else { $SdkRoot = Join-Path $toolsRoot 'sdk' }
}
if (-not $JdkRoot -or -not (Test-Path -LiteralPath (Join-Path $JdkRoot 'bin\javac.exe'))) {
  throw 'JDK 17 was not found. Install it and pass -JdkRoot or set JAVA_HOME.'
}
$JdkRoot = (Resolve-Path -LiteralPath $JdkRoot).Path
if (-not (Test-Path -LiteralPath $SdkRoot -PathType Container)) {
  throw 'The Android SDK was not found. Pass -SdkRoot or set ANDROID_HOME.'
}
$SdkRoot = (Resolve-Path -LiteralPath $SdkRoot).Path
$requiredSdkFiles = @{
  'platforms\android-36\android.jar' = 'platforms;android-36'
  'build-tools\35.0.0\aapt2.exe' = 'build-tools;35.0.0'
  'platform-tools\adb.exe' = 'platform-tools'
}
foreach ($relativePath in $requiredSdkFiles.Keys) {
  if (-not (Test-Path -LiteralPath (Join-Path $SdkRoot $relativePath) -PathType Leaf)) {
    throw "Missing Android SDK package: $($requiredSdkFiles[$relativePath]). Install it using Android Studio SDK Manager or sdkmanager."
  }
}
$env:JAVA_HOME = $JdkRoot
$env:ANDROID_HOME = $SdkRoot
$env:ANDROID_SDK_ROOT = $SdkRoot
$env:GRADLE_USER_HOME = Join-Path $toolsRoot 'gradle-home'
$javaVersion = & (Join-Path $JdkRoot 'bin\javac.exe') --version
if ($LASTEXITCODE -ne 0 -or $javaVersion -notmatch '^javac 17[\.-]') {
  throw "This build uses JDK 17. Found: $javaVersion. Pass -JdkRoot pointing at JDK 17."
}

$wrapperPath = Join-Path $androidProject 'gradlew.bat'
if (-not (Test-Path -LiteralPath $wrapperPath -PathType Leaf)) {
  throw 'The Gradle wrapper is missing from android-tv/.'
}
$distDir = Join-Path $projectRoot 'dist'
New-Item -ItemType Directory -Force -Path $distDir | Out-Null
$buildName = if ($LandscapeWifi) { 'nexus-signage-landscape-wifi' } else { 'nexus-signage-tv' }
$buildLog = Join-Path $distDir ($buildName + '-build.log')

Write-Host 'Building APK and running Android unit tests and lint...'
Push-Location -LiteralPath $androidProject
try {
  # PowerShell 5 wraps native stderr in ErrorRecord objects. Gradle's exit code
  # determines success; harmless native warning output must still reach the log.
  $ErrorActionPreference = 'Continue'
  $landscapeProperty = '-PlandscapeWifi=' + $LandscapeWifi.IsPresent.ToString().ToLowerInvariant()
  & $wrapperPath --no-daemon --console=plain $landscapeProperty :app:assembleDebug :app:testDebugUnitTest :app:lintDebug 2>&1 |
    ForEach-Object { $_.ToString() } |
    Tee-Object -FilePath $buildLog
  $gradleExit = $LASTEXITCODE
  $ErrorActionPreference = 'Stop'
  if ($gradleExit -ne 0) {
    throw "Android build or checks failed (exit $gradleExit). See $buildLog."
  }
} finally {
  $ErrorActionPreference = 'Stop'
  Pop-Location
}

$builtApk = Join-Path $androidProject 'app\build\outputs\apk\debug\app-debug.apk'
if (-not (Test-Path -LiteralPath $builtApk -PathType Leaf)) {
  throw 'Gradle completed but the expected debug APK is missing.'
}
$distApk = Join-Path $distDir ($buildName + '.apk')
Copy-Item -LiteralPath $builtApk -Destination $distApk -Force
$checksum = (Get-FileHash -LiteralPath $distApk -Algorithm SHA256).Hash.ToLowerInvariant()
Set-Content -LiteralPath ($distApk + '.sha256') -Value "$checksum  $buildName.apk" -Encoding ascii
Write-Host "APK ready: $distApk"
Write-Host "SHA-256: $checksum"
Write-Host "Unit test reports: $(Join-Path $androidProject 'app\build\reports\tests\testDebugUnitTest\index.html')"
Write-Host "Lint report: $(Join-Path $androidProject 'app\build\reports\lint-results-debug.html')"
