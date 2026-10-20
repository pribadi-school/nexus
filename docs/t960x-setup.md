# T960X vertical display — Nexus 1.3.0

Built for the supplied **T960X, Android 7.1.2 (API25), 2 GB, native portrait**
display. Supports video, image and website playlists and 4K display coordinates.
Physical playback and power-on still need checking on the display itself.

## Install once

1. Copy **nexus-signage-t960x-portrait-1.3.0.apk** to a USB drive and install it
   on the display. Allow installation from that USB/file manager if Android asks.
2. Open **Nexus Signage** once. Keep the server address
   **https://nexus.pribadidepok.sch.id**, then choose **Get pairing code**.
3. On your computer: **Nexus → Signage → Pair Android TV**. Enter the code and
   select the standing screen. In that screen's settings choose **Portrait**
   and **Fit** to show whole images/videos without cropping. Save its playlist.
4. On the display: **Set up automatic startup → Choose Nexus as Home**.
   Select **Nexus Signage**, then **Always** if asked. After pairing, hold your
   finger still for one second to open **Startup** or **Settings**.
5. Switch the display fully off and on once. Nexus should open and play its
   saved playlist automatically. Also check waking from standby. If the
   manufacturer's power-on setting offers Android or HDMI, choose Android.

Installing alone cannot select Home silently or start an app never opened.
Default Home is Android's supported automatic startup method. Keep the old
launcher installed so IT can restore it through Android settings if needed.

## Content and display

- **Videos:** play to their natural end. H.264 video with AAC audio in MP4 is
  the safest choice. A 4K panel does not guarantee its decoder supports every
  4K codec, bitrate or portrait-encoded video; test your actual files. MOV
  uploads retain their original codec.
- **Images:** use portrait PNG/JPG files, ideally 2160 × 3840 for 4K. Large
  images are decoded within a memory budget; JPEG camera rotation is respected.
  Choose their display duration in Nexus.
- **Websites:** use a desktop viewport scaled to the screen. They need internet
  and Android System WebView. A single-website playlist stays open for the
  site's own live updates. Websites without automatic updates still need their
  own refresh behavior. Android 7's WebView may not run every modern website;
  update the supported WebView through the vendor/Play Store when available.

Video/image files stay downloaded for offline playback. Websites need internet.
Screen/playlist changes are checked about every 30 seconds online.

**Settings → Display information** shows actual app output dimensions. A 4K
panel can still be configured by its firmware to output a lower resolution.
**Display orientation → Automatic (native screen)** is the default. Choose
Portrait manually only if this firmware opens Nexus sideways.

Playback and Nexus menus use immersive fullscreen: the status bar and Android
bottom navigation bar are hidden. Fullscreen is restored after dialogs,
standby and window focus changes. Normal Android can temporarily show its bars
after an edge swipe, and the pairing keyboard/System settings control their
own navigation. Managed kiosk mode below provides stronger system-navigation
locking. Verify that no bottom strip remains during a video, image and website
on this particular vendor firmware.

## Stronger lock to Nexus — optional, IT only

The APK supports Android device-owner kiosk mode. This sets Nexus as the
persistent Home app and locks other apps out while playback is active.
Ordinary installation does not grant that control.

With a computer already authorized for this display's USB debugging:

```sh
adb install -r nexus-signage-t960x-portrait-1.3.0.apk
adb shell dpm set-device-owner sch.id.pribadidepok.nexus.signage/.KioskAdmin
adb shell am start -n sch.id.pribadidepok.nexus.signage/.MainActivity
```

If Android rejects device ownership because accounts, another owner or vendor
restrictions are present, keep using default Home and share the error with IT.
Do not reset the display or delete accounts to force it. For maintenance, hold
the screen → **Settings → Pause managed kiosk lock**, then **System settings**.
Choose **Resume managed kiosk lock** afterwards. The setup page also has
Settings so IT can recover Wi-Fi before pairing.

See Android's [dedicated-device Home guide](https://developer.android.com/work/dpc/dedicated-devices/cookbook)
and [lock task guide](https://developer.android.com/work/dpc/dedicated-devices/lock-task-mode).

No cPanel database update is required for this APK.
