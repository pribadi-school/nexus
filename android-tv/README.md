# Nexus Signage for Android TV

The TV app pairs with Nexus, downloads assigned MP4/MOV videos and PNG/JPG images to private device
storage, and displays website playlist items while online. Nexus and its
original media files stay on your own hosting account.

Videos play once for their full file duration, then advance. Set image and
website display times in minutes in the Nexus screen playlist. Codec support
depends on the TV. Update installed TVs to version 1.2.0 for this behavior.

Version 1.2.1 fixes website sizing: a desktop viewport scales to fit the screen
regardless of Android display density. Single-website playlists remain open for
the website's live updates; mixed playlists retain configured display times.

## Build on Windows

Install JDK 17 and the Android SDK packages `platforms;android-36`,
`build-tools;35.0.0`, and `platform-tools` using Android Studio's SDK Manager.
Review and accept Google's SDK licenses when installing the tools.

From the repository root:

```powershell
powershell -ExecutionPolicy Bypass -File bin\build-android.ps1 `
  -JdkRoot C:\Java\jdk-17 -SdkRoot C:\Android\Sdk
```

The script also detects `JAVA_HOME` / `ANDROID_HOME`, or the local tools in
`.dev/android-tools/`. It runs APK assembly, unit tests, and Android lint. On
success it writes:

- `dist/nexus-signage-tv.apk`
- `dist/nexus-signage-tv.apk.sha256`
- `dist/android-build.log`

The Gradle 8.13 wrapper is included and verifies its downloaded distribution
against the pinned SHA-256 checksum. The first build needs internet access
for Gradle and Maven dependencies. There is no Android build step on cPanel.

Android Studio can also open this folder directly. On macOS/Linux, with
JDK 17 and `ANDROID_HOME` configured:

```sh
sh gradlew :app:assembleDebug :app:testDebugUnitTest :app:lintDebug
```

## Install and pair

Install the APK on an Android TV with Android 8.0 or newer. For a device
already connected through Android Debug Bridge:

```sh
adb install -r dist/nexus-signage-tv.apk
```

Open Nexus Signage on the TV and enter your Nexus server address. Sign in to
Nexus in your computer browser, open **Signage → Pair Android TV**, and
approve the six-digit TV code for an existing screen. Open that screen to
arrange its own playlist and save changes.

For automatic launch at power-on, select **Startup → Choose Nexus as Home**
once on each TV. The app also resumes playback after standby. Manufacturer
restrictions may require IT setup over ADB; see
[installation and automatic startup](../docs/android-tv.md).

The APK is a debug-signed build for local installation and testing. Preserve
the signing key used for installed TVs when preparing later updates; a
production release should use your own backed-up release signing key.
