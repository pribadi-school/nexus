package sch.id.pribadidepok.nexus.signage;

import android.annotation.SuppressLint;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.content.SharedPreferences;
import android.net.wifi.WifiManager;
import android.os.Build;
import android.os.Handler;
import android.os.HandlerThread;
import android.os.IBinder;
import android.os.PowerManager;
import android.os.SystemClock;
import android.provider.Settings;
import java.util.concurrent.atomic.AtomicBoolean;

/** Persistent recovery for powered Android 7–9 and compatible Android 10 builds. */
public final class WifiKeeperService extends Service {
    static final String ACTION_RETRY = "sch.id.pribadidepok.nexus.signage.RETRY_WIFI";
    private static final String CHANNEL = "nexus_wifi_keeper";
    private static final int NOTIFICATION = 4201;
    private static final long BOOT_SETTLE_MS = 90_000;
    private HandlerThread radioThread;
    private Handler timer;
    private CoalescedWork recovery;
    private final AtomicBoolean releaseForAirplane = new AtomicBoolean();
    private SharedPreferences settings;
    private WifiStartupSession session;
    private WifiManager.WifiLock wifiLock;
    private PowerManager.WakeLock cpuLock;
    private volatile boolean ready;
    private boolean stopping, receiverRegistered, preferenceRegistered, lockFailureLogged;
    private String notificationStatus = "Automatic Wi-Fi recovery is starting.";

    private final SharedPreferences.OnSharedPreferenceChangeListener preferenceListener = (preferences, key) -> {
        if (WifiStartup.PREFERENCE.equals(key) && !WifiStartup.enabled(this)) {
            stopWork();
            WifiStartup.publish(this, "Automatic Wi-Fi keeper is off.");
            stopSelf();
        }
    };
    private final BroadcastReceiver wakeReceiver = new BroadcastReceiver() {
        @Override public void onReceive(Context context, Intent intent) {
            if (!ready || recovery == null || !WifiStartup.enabled(context)) return;
            if (Intent.ACTION_AIRPLANE_MODE_CHANGED.equals(intent.getAction())) {
                // Request release even during policy backoff. Vendor binder
                // calls and lock cleanup stay on the radio worker.
                if (intent.getBooleanExtra("state", false)) releaseForAirplane.set(true);
            }
            recovery.request();
        }
    };

    // Runtime policy refuses API 30+ and modern-target apps on API 29. Service
    // types are not mandatory on the supported API 25–29 / target-28 path.
    @SuppressLint({"ForegroundServiceType", "UnspecifiedRegisterReceiverFlag"})
    @Override public void onCreate() {
        super.onCreate();
        settings = WifiStartup.preferences(this);
        if (!WifiStartup.supported(this) || !WifiStartup.enabled(this)) { stopSelf(); return; }
        try {
            startForeground(NOTIFICATION, notification(notificationStatus));
            ready = true;
        } catch (RuntimeException denied) {
            android.util.Log.e("NexusWifiKeeper", "Foreground Wi-Fi keeper could not start", denied);
            WifiStartup.publish(this, "Android could not keep Wi-Fi recovery running. Check this display's startup settings.");
            stopSelf();
            return;
        }
        // A Service shares the Activity's main thread by default. Vendor Wi-Fi
        // binder calls must never block playback, remote input or Settings.
        radioThread = new HandlerThread("NexusWifiRadio", android.os.Process.THREAD_PRIORITY_BACKGROUND);
        radioThread.start();
        timer = new Handler(radioThread.getLooper());
        session = new WifiStartupSession(new WifiStartupSession.Radio() {
            @Override public boolean airplaneMode() {
                if (!ready) return true;
                try {
                    boolean airplane = WifiKeeperService.this.airplaneMode();
                    if (airplane) releaseLocks(); else acquireLocks();
                    return airplane;
                } catch (RuntimeException unavailable) {
                    releaseLocks();
                    throw unavailable;
                }
            }
            @Override public WifiStartupSession.State state() {
                if (!ready) return WifiStartupSession.State.UNKNOWN;
                switch (manager().getWifiState()) {
                    case WifiManager.WIFI_STATE_DISABLED: return WifiStartupSession.State.DISABLED;
                    case WifiManager.WIFI_STATE_DISABLING: return WifiStartupSession.State.DISABLING;
                    case WifiManager.WIFI_STATE_ENABLING: return WifiStartupSession.State.ENABLING;
                    case WifiManager.WIFI_STATE_ENABLED: return WifiStartupSession.State.ENABLED;
                    default: return WifiStartupSession.State.UNKNOWN;
                }
            }
            @Override public boolean enable() {
                if (!ready || !WifiStartup.supported(WifiKeeperService.this) || !WifiStartup.enabled(WifiKeeperService.this)) return false;
                if (WifiKeeperService.this.airplaneMode()) { releaseLocks(); return false; }
                return manager().setWifiEnabled(true);
            }
        }, new WifiStartupSession.Timer() {
            @Override public long now() { return SystemClock.elapsedRealtime(); }
            @Override public void postDelayed(Runnable callback, long delay) { if (ready) timer.postDelayed(callback, delay); }
            @Override public void remove(Runnable callback) { timer.removeCallbacks(callback); }
        }, this::report);
        recovery = new CoalescedWork(callback -> timer.post(callback), () -> {
            if (!ready || !WifiStartup.enabled(this)) return;
            // Some Xiaomi Android 10 builds start with Wi-Fi off and wedge the
            // Wi-Fi service if it is enabled while boot is still settling. A
            // later request (like a person using Settings) works.
            long early = BOOT_SETTLE_MS - SystemClock.elapsedRealtime();
            if (early > 0) {
                report("Waiting for the TV to finish starting before checking Wi-Fi.");
                timer.postDelayed(recovery::request, early);
                return;
            }
            if (releaseForAirplane.getAndSet(false)) releaseLocks();
            session.start(true, true);
            session.wake();
        });
        settings.registerOnSharedPreferenceChangeListener(preferenceListener);
        preferenceRegistered = true;
        IntentFilter events = new IntentFilter(WifiManager.WIFI_STATE_CHANGED_ACTION);
        events.addAction(Intent.ACTION_SCREEN_ON);
        events.addAction(Intent.ACTION_USER_PRESENT);
        events.addAction(Intent.ACTION_AIRPLANE_MODE_CHANGED);
        try {
            // API 25–29 only: receiver export flags were introduced later.
            registerReceiver(wakeReceiver, events);
            receiverRegistered = true;
        } catch (RuntimeException denied) {
            // Polling still repairs the radio if vendor firmware blocks broadcasts.
            android.util.Log.w("NexusWifiKeeper", "Wi-Fi wake events unavailable; periodic recovery remains active", denied);
        }
    }

    @Override public int onStartCommand(Intent intent, int flags, int startId) {
        if (!ready || session == null || !WifiStartup.supported(this) || !WifiStartup.enabled(this)) {
            stopWork();
            stopSelf(startId);
            return START_NOT_STICKY;
        }
        // Repeated starts/wake events preserve the radio policy's retry cooldown.
        recovery.request();
        return START_STICKY;
    }

    private WifiManager manager() {
        WifiManager wifi = getApplicationContext().getSystemService(WifiManager.class);
        if (wifi == null) throw new IllegalStateException("Wi-Fi service is unavailable.");
        return wifi;
    }

    private boolean airplaneMode() {
        return Settings.Global.getInt(getContentResolver(), Settings.Global.AIRPLANE_MODE_ON, 0) != 0;
    }

    // An attended phone should use a timeout. This opt-in keeper is specifically
    // for a permanently powered signage display: release on disable, airplane
    // mode and destruction instead of expiring while nobody is at the screen.
    @SuppressLint("WakelockTimeout")
    private void acquireLocks() {
        if (!ready || !WifiStartup.enabled(this)) return;
        try {
            if (cpuLock == null) {
                PowerManager power = getSystemService(PowerManager.class);
                if (power == null) throw new IllegalStateException("Power service is unavailable.");
                cpuLock = power.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "nexus:WifiKeeper");
                cpuLock.setReferenceCounted(false);
            }
            if (!cpuLock.isHeld()) cpuLock.acquire();
            if (!ready) return;
            if (wifiLock == null) {
                wifiLock = manager().createWifiLock(WifiManager.WIFI_MODE_FULL_HIGH_PERF, "nexus:WifiKeeper");
                wifiLock.setReferenceCounted(false);
            }
            if (!wifiLock.isHeld()) wifiLock.acquire();
            lockFailureLogged = false;
        } catch (RuntimeException unavailable) {
            if (!lockFailureLogged) android.util.Log.w("NexusWifiKeeper", "Display firmware blocked Wi-Fi/CPU awake locks", unavailable);
            lockFailureLogged = true;
        }
    }

    private void releaseLocks() {
        try { if (wifiLock != null && wifiLock.isHeld()) wifiLock.release(); }
        catch (RuntimeException unavailable) { android.util.Log.w("NexusWifiKeeper", "Could not release Wi-Fi lock", unavailable); }
        try { if (cpuLock != null && cpuLock.isHeld()) cpuLock.release(); }
        catch (RuntimeException unavailable) { android.util.Log.w("NexusWifiKeeper", "Could not release CPU lock", unavailable); }
    }

    // Like onCreate, this path is restricted to supported legacy service rules.
    @SuppressLint("ForegroundServiceType")
    private void report(String detail) {
        if (!ready) return;
        WifiStartup.publish(this, detail);
        if (!WifiStartup.supported(this)) return;
        if (!ready || detail.equals(notificationStatus)) return;
        notificationStatus = detail;
        try {
            // Updating the same foreground notification keeps its service
            // ownership; no separate notification permission is needed.
            startForeground(NOTIFICATION, notification(detail));
        } catch (RuntimeException unavailable) { android.util.Log.w("NexusWifiKeeper", "Could not update Wi-Fi status notification", unavailable); }
    }

    private Notification notification(String detail) {
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationManager notifications = getSystemService(NotificationManager.class);
            if (notifications != null) {
                NotificationChannel channel = new NotificationChannel(CHANNEL, "Nexus Wi-Fi recovery", NotificationManager.IMPORTANCE_LOW);
                channel.setDescription("Keeps this signage display connected without manual intervention.");
                channel.setShowBadge(false);
                notifications.createNotificationChannel(channel);
            }
        }
        Intent open = new Intent(this, MainActivity.class).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        PendingIntent content = PendingIntent.getActivity(this, 0, open, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
        Notification.Builder builder = Build.VERSION.SDK_INT >= 26 ? new Notification.Builder(this, CHANNEL) : new Notification.Builder(this);
        return builder.setSmallIcon(R.drawable.ic_nexus).setContentTitle("Nexus Wi-Fi keeper").setContentText(detail)
                .setContentIntent(content).setOngoing(true).setOnlyAlertOnce(true).setShowWhen(false)
                .setCategory(Notification.CATEGORY_SERVICE).setPriority(Notification.PRIORITY_LOW).build();
    }

    private void stopWork() {
        if (stopping) return;
        stopping = true;
        ready = false;
        if (recovery != null) recovery.close();
        if (timer != null) {
            timer.removeCallbacksAndMessages(null);
            // Do not join or release a vendor Wi-Fi lock on the main thread.
            // If IPC is stalled, cleanup proceeds when that call returns.
            timer.post(() -> {
                if (session != null) session.stop();
                releaseLocks();
                radioThread.quitSafely();
            });
        }
        stopForeground(true);
    }

    @Override public void onDestroy() {
        stopWork();
        if (preferenceRegistered) { settings.unregisterOnSharedPreferenceChangeListener(preferenceListener); preferenceRegistered = false; }
        if (receiverRegistered) {
            try { unregisterReceiver(wakeReceiver); } catch (IllegalArgumentException ignored) { /* Already unregistered by vendor teardown. */ }
            receiverRegistered = false;
        }
        super.onDestroy();
    }

    @Override public IBinder onBind(Intent intent) { return null; }
}
