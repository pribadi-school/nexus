# Nexus Network Rescue

Standalone, locally installed maintenance app for Android 7.1–10 (API 25–29).
This is not a signage-player update. Its application ID is
`sch.id.pribadidepok.nexus.rescue`. It does not become Home, start at boot,
disable apps, clear data, read saved passwords, or change Nexus pairing.

On launch it checks Wi-Fi state, local IPv4 addresses,
the enabled state of Android TV Settings and Google TV Home, and Home resolution.
Checks run on separate workers. After eight seconds without a reply, the screen
reports that the Android call is still pending. A timeout cannot cancel a vendor
Binder call; duplicate requests of that kind remain blocked until it returns.

Two manual buttons request `WifiManager.setWifiEnabled(true)` or
`WifiManager.reconnect()`. API 29 retains these legacy operations for apps
targeting API 28. A true result means Android accepted a request, not that a
network or internet connection succeeded. Both operations refuse Android 11+
and airplane mode. No network is scanned, added, removed, or reconfigured.
The tool can only ask Android to reconnect using existing saved configuration.
It cannot repair a blocked system service, set missing credentials, enable ADB,
or obtain privileged permissions. It makes no outbound network requests.

Official API reference:
https://developer.android.com/reference/android/net/wifi/WifiManager

Build from `android-tv` with the configured JDK/SDK and Gradle cache:

    .\gradlew.bat --no-daemon --console=plain -PnetworkRescue=true :rescue:assembleDebug :rescue:testDebugUnitTest :rescue:lintDebug

Install separately through TV Manager's **Install via USB**. Open **Nexus Network
Rescue** and photograph its initial report before changing Wi-Fi. If the radio
reports OFF, press **Turn Wi-Fi ON**, wait 15 seconds, then **Check network again**.
If it reports ON without a connection, press **Reconnect saved Wi-Fi**, wait 15
seconds, then check again. Photograph any failure or pending-call report.

Retain Google TV Home during maintenance. The rescue app may be uninstalled
after recovery without affecting Nexus Signage or the original Home app.
