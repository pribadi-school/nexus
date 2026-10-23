; Nexus Bell: Windows installer for the bell PC. Built by bell-pc\build-installer.ps1.
;
; Per-user install (no administrator needed): the program keeps its settings,
; downloaded sounds and logs in its own folder, so it must live somewhere the
; signed-in user can write. "Start with Windows" uses the same current-user Run
; entry as the app's own Settings checkbox, so the two always agree.

#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif
#ifndef ExeSource
  #define ExeSource "..\dist\NexusBell.exe"
#endif

#define AppName "Nexus Bell"
#define AppExe "NexusBell.exe"

[Setup]
AppId={{BEAE12C0-0519-430A-AFA1-D13ABEA8891F}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} {#AppVersion}
AppPublisher=Sekolah Pribadi Depok
AppPublisherURL=https://nexus.pribadidepok.sch.id
DefaultDirName={localappdata}\Programs\NexusBell
DefaultGroupName={#AppName}
DisableProgramGroupPage=yes
PrivilegesRequired=lowest
OutputDir=Output
OutputBaseFilename=NexusBell-Setup-{#AppVersion}
SetupIconFile=..\..\public\favicon.ico
UninstallDisplayIcon={app}\NexusBell.ico
UninstallDisplayName={#AppName}
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
ArchitecturesInstallIn64BitMode=x64compatible
CloseApplications=no
; A newer install replaces an older one in place; settings and logs are never touched.
UsePreviousAppDir=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[CustomMessages]
english.TaskAutostart=Start with Windows, quietly in the taskbar (recommended) / Mulai bersama Windows
english.TaskDesktop=Create a desktop shortcut / Buat pintasan di desktop
english.RunNow=Open Nexus Bell now / Buka Nexus Bell sekarang
english.KeepData=Nexus Bell has been removed.%n%nDo you also want to delete its settings, the downloaded sounds and the logs?%n%nChoose No to keep them in case you install it again. (Hapus juga pengaturan, suara dan log?)

[Tasks]
Name: "autostart"; Description: "{cm:TaskAutostart}"; GroupDescription: "Options:"
Name: "desktopicon"; Description: "{cm:TaskDesktop}"; GroupDescription: "Options:"; Flags: unchecked

[Dirs]
; Settings (pengaturan.json), sounds (suara) and logs (log) are created here by the app.
Name: "{app}"

[Files]
Source: "{#ExeSource}"; DestDir: "{app}"; DestName: "{#AppExe}"; Flags: ignoreversion
Source: "..\..\public\favicon.ico"; DestDir: "{app}"; DestName: "NexusBell.ico"; Flags: ignoreversion

[Icons]
Name: "{group}\{#AppName}"; Filename: "{app}\{#AppExe}"; IconFilename: "{app}\NexusBell.ico"
Name: "{group}\Nexus Bell logs (log)"; Filename: "{app}\log"; Check: LogFolderExists
Name: "{group}\Uninstall {#AppName}"; Filename: "{uninstallexe}"
Name: "{userdesktop}\{#AppName}"; Filename: "{app}\{#AppExe}"; IconFilename: "{app}\NexusBell.ico"; Tasks: desktopicon

[Registry]
; Same key, name and command the app writes itself (winutil.py), removed on uninstall.
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "NexusBell"; ValueData: """{app}\{#AppExe}"" --minimized"; Tasks: autostart; Flags: uninsdeletevalue

[Run]
Filename: "{app}\{#AppExe}"; Description: "{cm:RunNow}"; Flags: nowait postinstall skipifsilent

[Code]
function LogFolderExists: Boolean;
begin
  Result := DirExists(ExpandConstant('{app}\log'));
end;

// The installed copy (a tray program) cannot be replaced or removed while it runs.
// Stop only the process started from the install folder: another copy running elsewhere,
// such as one someone is testing from a USB drive, is left alone.
procedure StopApp;
var
  Code: Integer;
  Path, Command: String;
begin
  Path := ExpandConstant('{app}\{#AppExe}');
  StringChangeEx(Path, '''', '''''', True);
  Command := '-NoProfile -ExecutionPolicy Bypass -Command "Get-Process NexusBell -ErrorAction SilentlyContinue | ' +
    'Where-Object { $_.Path -eq ''' + Path + ''' } | Stop-Process -Force"';
  Exec(ExpandConstant('{sys}\WindowsPowerShell\v1.0\powershell.exe'), Command, '', SW_HIDE, ewWaitUntilTerminated, Code);
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  StopApp;
  Result := '';
end;

function InitializeUninstall: Boolean;
begin
  StopApp;
  Result := True;
end;

// Offer to delete the settings, sounds and logs. The default is to keep them.
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
    if not UninstallSilent then
      if MsgBox(CustomMessage('KeepData'), mbConfirmation, MB_YESNO or MB_DEFBUTTON2) = IDYES then
        DelTree(ExpandConstant('{app}'), True, True, True);
end;
