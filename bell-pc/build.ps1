# Builds dist\NexusBell.exe (one file, no console window).
# Run on a Windows PC with Python 3.11+ installed:  powershell -ExecutionPolicy Bypass -File build.ps1
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
python -m pip install -r requirements.txt
python -m unittest discover -s tests -v
if ($LASTEXITCODE -ne 0) { throw 'Tests failed; the exe was not built.' }
python -m PyInstaller --noconfirm --onefile --windowed --name NexusBell `
  --add-data "sounds;sounds" `
  --collect-all sounddevice --collect-all soundfile `
  --hidden-import pystray._win32 `
  main.py
Write-Host 'Done: dist\NexusBell.exe'
