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

Version **1.3.2** replaces the one-shot Wi-Fi startup check with **Settings →
Wi-Fi keeper → Keep Wi-Fi on 24/7**, enabled by default on Android 7–9. The old
check could be cancelled when the activity paused and did not resume itself;
it could also finish before the TV's Wi-Fi service was ready. The keeper is a
persistent foreground service launched at boot, package update and Nexus resume,
independently of pairing. It monitors the actual radio state and requests Wi-Fi
again when off. Failed attempts retry with 15/30/60-second cooldowns rather than
requiring the user to press Retry. Pausing the activity or putting the display
in standby does not stop the keeper. Its ongoing notification identifies the
service, and Wi-Fi/CPU wake locks support unattended use on a powered display.

The setting retains the existing `wifi_startup` preference. Disable it before
intentionally turning Wi-Fi off for maintenance; otherwise Nexus turns the radio
back on. Airplane mode suspends requests and releases locks. Disabling or stopping
the keeper also releases its locks. Radio-on status does not imply internet or
authentication succeeded: Android manages reconnecting to existing saved networks.
Nexus immediately schedules a playlist sync on network restoration and retains
downloaded media when offline. No saved network, credential, DHCP, sleep or hotspot
setting is modified, and no Wi-Fi scan is used.

In the standard modern-target build, automatic radio control is unavailable on
Android 10+ and no toggle request is made. Wi-Fi hardware remains optional. Hardware/driver failure, lost power,
Force stop and vendor restrictions cannot be bypassed by an ordinary APK. Verify
cold boot, standby/wake and radio-off recovery on each physical TV; local builds
do not prove those behaviors. See [Android's Wi-Fi API](https://developer.android.com/reference/android/net/wifi/WifiManager#setWifiEnabled(boolean)).

### Android 10 landscape Wi-Fi build (1.4.0)

For Kantin, SD and Lantai 3 Xiaomi MiTV-MOOQ1 TVs running Android 10, build with
`bin/build-android.ps1 -LandscapeWifi`. The output is
`dist/nexus-signage-landscape-wifi.apk`. The build shares the latest player code,
package identity and signing key with earlier APKs, defaults to landscape, and
retains the 1.2.1 desktop website sizing and all subsequent playback fixes.

This dedicated sideload build intentionally targets API 28. Android 10 permits
apps with this compatibility target to request `WifiManager.setWifiEnabled(true)`
with `CHANGE_WIFI_STATE`; a target-36 build cannot make the same request. The
keeper checks both the running OS and the installed APK's actual target before
starting. It runs on Android 7.1–9, plus Android 10 when the target is <= 28;
it does not start on Android 11+. Standard builds continue targeting API 36.
See [Android's compatibility rule](https://developer.android.google.cn/reference/android/net/wifi/WifiManager.html#setWifiEnabled(boolean)).

The keeper defaults on and retains an explicitly disabled preference. It keeps
the Wi-Fi/CPU awake locks while active and retries radio activation automatically
with capped cooldowns. Android reconnects to the saved network: the app does not
change passwords or network configuration, and radio-on does not prove internet
access. Airplane mode and switching the keeper off suspend recovery. Force stop,
power loss, firmware restrictions, failed hardware and unavailable access points
cannot be overcome by the APK.

Hold the remote's **Back button for two seconds** for Wi-Fi Settings, or press
**Menu → Wi-Fi settings**. The Settings key opens Android Settings. These paths
work during setup, pairing and offline playback, without relying on the Home
launcher or a response from Nexus. Setup and player message screens also offer
a Wi-Fi Settings button. If a vendor blocks an action, Nexus tries alternate
Settings actions and reports failure. Managed kiosk lock is temporarily stopped
for maintenance and reapplied on return to Nexus.

Choose **Update**, never uninstall, to preserve pairing, saved content and the
existing Home selection. Test radio-off recovery, boot, standby/wake, portrait
and landscape content, website scaling and offline Settings on one physical TV
before updating the rest. A TV already disconnected cannot receive this APK
over Wi-Fi; restore local/USB or network access first.

### Recovery isolation (1.4.1)

The 1.4.0 keeper performed Wi-Fi Binder calls on the main service thread, which
is also the player's UI thread. A stalled vendor Wi-Fi API could therefore
freeze remote input. Version 1.4.1 dispatches all Wi-Fi, airplane-state and
awake-lock work to a background HandlerThread. Repeated events are coalesced;
shutdown invalidates queued work without joining a potentially blocked thread.
An in-flight Binder call cannot be forcibly cancelled: its cleanup waits for
the call to return, while player input and recovery remain independent.

The Wi-Fi/System settings shortcuts now open the separate **Nexus Recovery**
activity/process, also listed as a maintenance app in the TV app list. It uses
plain native buttons, leaves system navigation visible, and performs no player,
Wi-Fi-service or network initialization. It temporarily stops the keeper;
returning to the player resumes the keeper according to its existing setting.
No SharedPreferences are read or written from the recovery process.

On the matching MiTV-MOOQ1, read-only resolution confirmed these available
Android TV activities: NetworkActivity, MainSettings, AppsActivity and the
Google TV package's AppManagementActivity. Recovery tries explicit network/
Settings/Apps activities followed by standard Settings actions, and reports
when no target opens. The Google TV app-info button only opens its page: it
does not re-enable the launcher, clear data or change the Home role itself.

If opening Android Wi-Fi still freezes, return using Back or Home and try
**Open Apps settings** or **Open Google TV app settings**. If the latter opens
and the TV shows **Enable**, enabling Google TV is a manual recovery option
that restores its features and can change Home-button behavior. A running
Android Settings process can still hang independently of Nexus; the new
activity is not a firmware repair. These fixes address identified risks;
Kantin's actual failure cannot be confirmed without device logs.

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
   Settings. Back reveals/hides controls; holding it for two seconds opens Wi-Fi
   Settings. Menu opens settings even before pairing.

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
