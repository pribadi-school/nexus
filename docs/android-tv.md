# Nexus Signage for Android TV

The native player uses the existing Nexus web management and cPanel media
storage. Downloaded MP4s play from the TV's private app storage; website items
use the TV's WebView while connected. No AbleSign account is required.

## Installation and pairing

1. Deploy Nexus, then import `007_signage_pairing.sql` after migration 006.
   No worker or new PHP extensions are needed. Use your public HTTPS address.
2. Create content, a playlist and a screen in Nexus → Signage.
3. Install `dist/nexus-signage-tv.apk` on the TV using USB or your usual Android
   installation method. The minimum version is Android 8.0 (API 26).
4. Open **Nexus Signage**, enter the Nexus server address, and request a code.
5. In the web app, open **Signage → Pair Android TV**, enter the six-digit code,
   and choose the screen. Codes expire after ten minutes.
6. Keep the TV connected until its first playlist has downloaded. Use the
   remote's OK/Menu button for controls, including Sync now, Next, Startup and
   Settings. Back reveals controls, then follows normal Android navigation.

The TV needs network access to the server. `localhost` on the TV points at the
TV, not your development computer. For LAN testing, use the computer's private
IP and allow it through the firewall if necessary. The existing local dev
server binds to 127.0.0.1, so public cPanel deployment or an explicitly
LAN-bound development server is required for a physical TV.

## Automatic startup — configure once on each TV

Select **Startup → Choose Nexus as Home**, then choose Nexus Signage as the
default Home app. Android itself then launches Nexus as its home screen when
the TV boots. Playback resumes when the app returns from standby. This setup
uses standard Android Home-app selection; it does not require accessibility,
overlay, or device-administrator permissions.

Some TV manufacturers hide or restrict Home-app selection. The Startup dialog
explains this rather than pretending a boot receiver guarantees launch on
every Android version. An administrator can configure the installed app over
ADB on supported TVs:

```powershell
adb connect TV_IP:5555
adb install -r "dist/nexus-signage-tv.apk"
adb shell cmd package set-home-activity --user 0 sch.id.pribadidepok.nexus.signage/.MainActivity
adb shell am start -a android.intent.action.MAIN -c android.intent.category.HOME
```

The TV's developer/ADB access and any pairing prompts must be configured using
the manufacturer's instructions. To restore its original launcher, select the
previous Home app in Android settings or use the same ADB command with the
original launcher's component. Record that original component before changing
it. Nexus's **Settings → System settings** remains available.

The app also listens for boot completion and package replacement. On older
Android versions it attempts to launch after boot; modern Android restricts
background activity launches, so the default Home method is the reliable
startup path. Installation, initial pairing and startup selection are one-time
steps. An APK cannot bypass Android's initial stopped-app state, a deliberate
Force stop, manufacturer restrictions, or a TV configured to power on to a
different input. Test a full power-off/on and standby cycle on each TV model.

## Offline playback and updates

- A new playlist becomes active only after all its MP4 files have downloaded
  with the expected file lengths. Interrupted downloads retain the previous
  complete playlist. Videos are not downloaded again on each loop.
- The saved playlist and files survive app restarts. The player uses up to
  2 GB of video storage and reserves at least 100 MB of free disk when an update
  needs downloads. Old video versions are removed after the new playlist is
  activated and the old player is released.
- Website items require connectivity; the player skips them while offline and
  continues downloaded videos. A playlist containing only websites waits for
  reconnection. WebView navigation is HTTP(S) only, file access is disabled,
  and certificate errors are never bypassed.
- Screen changes are polled every 30 seconds while the app is open. Pausing in
  Nexus stops playback at the next successful check. A known revoked/deleted
  screen clears its local key and playlist; network loss alone retains video.
- Support for an MP4's codec depends on the TV. H.264 video and AAC audio give
  broad compatibility. Check your actual content on one TV before rollout.

## Building and updating the APK

The Android source is in `android-tv/`; use JDK 17, Android SDK platform 36,
Build Tools 35.0.0 and the committed Gradle 8.13 wrapper:

```powershell
powershell -ExecutionPolicy Bypass -File bin/build-android.ps1
```

The script builds the signed debug APK, runs JVM unit tests and Android lint,
then writes `dist/nexus-signage-tv.apk`, its SHA-256 file and build log. This
is an initial sideload build for testing. Keep its signing key safe if updating
the same installation; use a backed-up, school-controlled release key before
wider distribution. Build tools, downloaded dependencies, keystores and APKs
are excluded from Git. Keep PHP sources/migrations and both media/database
backups in your normal deployment process.

The web player remains available independently; web-preview behavior does not
prove Android TV playback or startup. Those need verification on a TV or an
Android emulator.
