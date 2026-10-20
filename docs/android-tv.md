# Nexus Signage for Android TV

The native player uses the existing Nexus web management and cPanel media
storage. Downloaded MP4/MOV videos and PNG/JPG images play from the TV's private app storage; website items
use the TV's WebView while connected. No AbleSign account is required.

TV app version 1.2.0 plays each video for its full file duration, then advances.
Update installed TVs to apply this behavior; old video time overrides are ignored.
Set image and website display durations in minutes in the left playlist
(0.5 minutes = 30 seconds). Playlist totals combine those durations with each
video's detected file length. MOV uploads retain
their original encoding, so playback depends on the TV or browser's codec support.

TV app version 1.2.1 gives website items a desktop viewport of at least 1,280
CSS pixels and scales it proportionally into the display area. This avoids
mobile breakpoints caused by Android TV's display density, supports portrait
output, and retains the installed WebView's Chromium version in the desktop
user agent. Install the APK update to apply these website changes.

A playlist containing just one website keeps that page open. The website's own
live polling or streaming updates continue without a manual refresh or periodic
playlist reload. A website that does not update itself will still need its own
refresh mechanism. Mixed playlists continue using each website's display time.

Version **1.3.0** supports Android **7.1/API25** and native portrait displays,
including the school's T960X Android 7.1.2 standing panel. The app follows
Android's native orientation instead of forcing landscape. Native portrait is
not rotated again when the Nexus screen is set to Portrait. The existing
rotation for physically upright landscape TVs remains available. Hold a finger
still for one second to open controls on touch displays. Controls wrap to fit
narrow screens. **Settings → Display orientation** offers native, portrait and
landscape output; **Display information** shows actual app output dimensions.
Images are sampled to a screen/heap budget and JPEG EXIF orientation is honored.
Image decoding runs separately from downloads so playlist updates do not queue
image playback behind a large transfer. See [T960X quick setup](t960x-setup.md).

Immersive fullscreen hides both Android system bars and lays playback across
the full window. It is reapplied after resume, focus changes and persistent
system-bar visibility changes; player menus apply the same flags before they
open. Recovery callbacks are event-driven and stop when the app is paused.
Android can briefly expose transient bars after an edge swipe, and external
Settings/keyboards retain their system navigation. Device-owner kiosk mode
provides the stronger navigation lock.

## Installation and pairing

1. Deploy Nexus, then import `007_signage_pairing.sql` after migration 006.
   No worker or new PHP extensions are needed. Use your public HTTPS address.
2. Add content and a screen in Nexus → Signage. Open the screen to arrange
   its own playlist, then save changes.
3. Install `dist/nexus-signage-tv.apk` on the TV using USB or your usual Android
   installation method. The minimum version is Android 7.1 (API 25).
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
overlay, or device-administrator permissions for ordinary default-Home startup.

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

Version 1.3.0 also includes an optional device-owner component, `.KioskAdmin`,
for a fully managed school display. Explicit IT provisioning grants ownership;
installing the APK alone does not. When provisioned, Nexus sets a persistent
preferred Home activity, allowlists itself and enters lock task mode on resume.
It does not disable the original launcher. Hold the screen → **Settings → Pause
managed kiosk lock** before opening System settings for maintenance; Settings
is also available before pairing. The kiosk pause choice is saved. See the
[T960X guide](t960x-setup.md) for provisioning and recovery, and Android's
[dedicated-device guide](https://developer.android.com/work/dpc/dedicated-devices/cookbook).

## Offline playback and updates

- A new playlist becomes active only after all its video and image files have downloaded
  with the expected file lengths. Interrupted downloads retain the previous
  complete playlist. Videos are not downloaded again on each loop.
- The saved playlist and files survive app restarts. The player uses up to
  2 GB of media storage and reserves at least 100 MB of free disk when an update
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
