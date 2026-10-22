<#
  Nexus TV helper: runs TV control actions from the Nexus website over ADB.

  The website cannot reach TVs on the school network, so this script runs on an
  always-on school PC, asks Nexus every few seconds for queued actions and runs
  only these fixed ones: open_nexus, status, screenshot, restart. Before any
  action it checks the TV's serial number, so a reused IP address can never
  control the wrong TV.

  Setup and auto-start: docs/tv-agent.md. Config: tv-agent.json next to this
  script (copy tv-agent.example.json). Windows PowerShell 5.1, no installs
  except Android platform-tools (adb.exe).
#>
param(
    [string]$Config = (Join-Path $PSScriptRoot 'tv-agent.json'),
    [switch]$Once   # one poll, for testing
)

$ErrorActionPreference = 'Stop'
$Version = '1.0.0'
$Package = 'sch.id.pribadidepok.nexus.signage'

$settings = Get-Content -Raw -LiteralPath $Config | ConvertFrom-Json
$base = ([string]$settings.url).TrimEnd('/')
$adb = [string]$settings.adb
$interval = if ($settings.poll_seconds) { [Math]::Max(5, [int]$settings.poll_seconds) } else { 10 }
$name = if ($settings.name) { [string]$settings.name } else { $env:COMPUTERNAME }
$screens = @{}
foreach ($tv in $settings.screens) { $screens[[int]$tv.screen_id] = $tv }
$logFile = Join-Path $PSScriptRoot 'tv-agent.log'

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
Add-Type -AssemblyName System.Drawing

function Write-Log([string]$text) {
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $text
    Write-Host $line
    try {
        if ((Test-Path $logFile) -and (Get-Item $logFile).Length -gt 1MB) { Move-Item $logFile "$logFile.old" -Force }
        Add-Content -LiteralPath $logFile -Value $line
    } catch { }
}

function Invoke-Nexus([string]$path, [hashtable]$body) {
    $headers = @{ 'X-Nexus-Agent-Token' = [string]$settings.token; 'X-Requested-With' = 'fetch'; Accept = 'application/json' }
    Invoke-RestMethod -Uri "$base$path" -Method Post -Headers $headers -Body $body -TimeoutSec 60
}

# adb with a time limit: a TV that drops off Wi-Fi must not hang the helper.
function Invoke-Adb([string[]]$arguments, [int]$seconds = 25) {
    $info = New-Object Diagnostics.ProcessStartInfo
    $info.FileName = $adb
    $info.Arguments = ($arguments | ForEach-Object { if ($_ -match '\s') { '"' + $_ + '"' } else { $_ } }) -join ' '
    $info.UseShellExecute = $false
    $info.RedirectStandardOutput = $true
    $info.RedirectStandardError = $true
    $info.CreateNoWindow = $true
    $process = [Diagnostics.Process]::Start($info)
    $out = $process.StandardOutput.ReadToEndAsync()
    $err = $process.StandardError.ReadToEndAsync()
    if (-not $process.WaitForExit($seconds * 1000)) {
        try { $process.Kill() } catch { }
        throw "The TV did not answer within $seconds seconds."
    }
    return (($out.Result + $err.Result).Trim())
}

function Connect-Tv($tv) {
    $target = [string]$tv.address
    if ($target -notmatch ':') { $target = "${target}:5555" }
    $connected = Invoke-Adb @('connect', $target)
    if ($connected -notmatch 'connected to|already connected') {
        throw "Cannot reach the TV at $target (is it on, on Wi-Fi, with debugging allowed?)."
    }
    $serial = Invoke-Adb @('-s', $target, 'shell', 'getprop', 'ro.serialno')
    if ($serial -match 'unauthorized') { throw 'The TV has not allowed debugging from this PC yet. Choose Allow on the TV.' }
    if ($serial.Trim() -ne [string]$tv.serial) {
        throw "A different TV answered at $target (serial $($serial.Trim())). Update its address in tv-agent.json."
    }
    return $target
}

function Get-Screenshot([string]$target) {
    $temp = Join-Path $env:TEMP ('nexus-tv-' + [guid]::NewGuid() + '.png')
    try {
        $null = Invoke-Adb @('-s', $target, 'shell', 'screencap', '-p', '/sdcard/nexus-shot.png') 30
        $null = Invoke-Adb @('-s', $target, 'pull', '/sdcard/nexus-shot.png', $temp) 60
        $null = Invoke-Adb @('-s', $target, 'shell', 'rm', '-f', '/sdcard/nexus-shot.png')
        $image = [Drawing.Image]::FromFile($temp)
        try {
            # 1280 px wide JPEG keeps uploads small on the hosting account.
            $scale = [Math]::Min(1.0, 1280 / $image.Width)
            $bitmap = New-Object Drawing.Bitmap ([int]($image.Width * $scale)), ([int]($image.Height * $scale))
            $graphics = [Drawing.Graphics]::FromImage($bitmap)
            $graphics.DrawImage($image, 0, 0, $bitmap.Width, $bitmap.Height)
            $graphics.Dispose()
            $codec = [Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object MimeType -eq 'image/jpeg'
            $quality = New-Object Drawing.Imaging.EncoderParameters 1
            $quality.Param[0] = New-Object Drawing.Imaging.EncoderParameter ([Drawing.Imaging.Encoder]::Quality), 80L
            $stream = New-Object IO.MemoryStream
            $bitmap.Save($stream, $codec, $quality)
            $bitmap.Dispose()
            return [Convert]::ToBase64String($stream.ToArray())
        } finally { $image.Dispose() }
    } finally { Remove-Item $temp -ErrorAction SilentlyContinue }
}

function Invoke-Action($command) {
    $tv = $screens[[int]$command.screen_id]
    if ($null -eq $tv) { return @{ ok = $false; message = 'This helper has no TV for that screen.' } }
    $target = Connect-Tv $tv
    switch ([string]$command.action) {
        'open_nexus' {
            $out = Invoke-Adb @('-s', $target, 'shell', 'am', 'start', '-n', "$Package/.MainActivity")
            if ($out -match 'Error') { return @{ ok = $false; message = "Could not open Nexus: $out" } }
            return @{ ok = $true; message = 'Nexus opened on the TV.' }
        }
        'restart' {
            $null = Invoke-Adb @('-s', $target, 'reboot') 15
            return @{ ok = $true; message = 'Restart sent. The TV is back in about two minutes.' }
        }
        'status' {
            $top = Invoke-Adb @('-s', $target, 'shell', 'dumpsys activity activities | grep mResumedActivity | head -1')
            $wifi = Invoke-Adb @('-s', $target, 'shell', 'dumpsys wifi | grep mNetworkInfo | head -1')
            $up = Invoke-Adb @('-s', $target, 'shell', 'uptime')
            $app = if ($top -match "$Package/") { 'Nexus is on screen' } elseif ($top -match 'tvlauncher') { 'Google TV home is on screen' } elseif ($top -match 'tv\.settings') { 'Settings is open' } else { 'Another app is on screen' }
            $net = if ($wifi -match 'CONNECTED/CONNECTED') { 'Wi-Fi connected' } else { 'Wi-Fi not connected (using another network?)' }
            $since = if ($up -match 'up\s+([^,]+(?:,\s*\d+:\d+)?)') { "on for $($Matches[1].Trim())" } else { '' }
            return @{ ok = $true; message = (@($app, $net, $since) | Where-Object { $_ }) -join ', ' }
        }
        'screenshot' {
            return @{ ok = $true; message = 'Screenshot taken.'; screenshot = (Get-Screenshot $target) }
        }
        default { return @{ ok = $false; message = 'Unknown action.' } }
    }
}

Write-Log "Nexus TV helper $Version started as '$name' for screens $(@($screens.Keys) -join ', ')."
while ($true) {
    try {
        $reply = Invoke-Nexus '/signage/api/tv-agent/poll' @{ name = $name; screens = (@($screens.Keys) -join ','); version = $Version }
        foreach ($command in @($reply.commands)) {
            if ($null -eq $command) { continue }
            Write-Log "Running $($command.action) for screen $($command.screen_id) (#$($command.id))."
            try { $result = Invoke-Action $command }
            catch { $result = @{ ok = $false; message = $_.Exception.Message } }
            $body = @{ id = [string]$command.id; ok = $(if ($result.ok) { '1' } else { '0' }); message = [string]$result.message }
            if ($result.screenshot) { $body.screenshot = $result.screenshot }
            try { $null = Invoke-Nexus '/signage/api/tv-agent/result' $body; Write-Log "  -> $($result.message)" }
            catch { Write-Log "  Could not report the result: $($_.Exception.Message)" }
        }
    } catch {
        Write-Log "Cannot reach Nexus: $($_.Exception.Message)"
    }
    if ($Once) { break }
    Start-Sleep -Seconds $interval
}
