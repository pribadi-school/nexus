package sch.id.pribadidepok.nexus.signage;

import android.content.Context;
import android.content.SharedPreferences;
import android.net.wifi.WifiManager;
import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.os.SystemClock;
import android.provider.Settings;
import java.util.function.Consumer;

/** Legacy Android 7–9 startup support; no scans, credentials or network edits. */
final class WifiStartup {
    private static final String PREFERENCE = "wifi_startup";
    private final SharedPreferences settings;
    private final WifiStartupSession session;

    WifiStartup(Context context, Consumer<String> report) {
        Context app = context.getApplicationContext();
        settings = app.getSharedPreferences("nexus", Context.MODE_PRIVATE);
        Handler handler = new Handler(Looper.getMainLooper());
        session = new WifiStartupSession(new WifiStartupSession.Radio() {
            private WifiManager manager() {
                WifiManager manager = app.getSystemService(WifiManager.class);
                if (manager == null) throw new IllegalStateException("Wi-Fi service is unavailable.");
                return manager;
            }
            @Override public boolean airplaneMode() {
                return Settings.Global.getInt(app.getContentResolver(), Settings.Global.AIRPLANE_MODE_ON, 0) != 0;
            }
            @Override public WifiStartupSession.State state() {
                switch (manager().getWifiState()) {
                    case WifiManager.WIFI_STATE_DISABLED: return WifiStartupSession.State.DISABLED;
                    case WifiManager.WIFI_STATE_DISABLING: return WifiStartupSession.State.DISABLING;
                    case WifiManager.WIFI_STATE_ENABLING: return WifiStartupSession.State.ENABLING;
                    case WifiManager.WIFI_STATE_ENABLED: return WifiStartupSession.State.ENABLED;
                    default: return WifiStartupSession.State.UNKNOWN;
                }
            }
            @Override public boolean enable() {
                // Newer Android versions require the system's own Wi-Fi UI.
                return supported() && manager().setWifiEnabled(true);
            }
        }, new WifiStartupSession.Timer() {
            @Override public long now() { return SystemClock.elapsedRealtime(); }
            @Override public void postDelayed(Runnable callback, long delay) { handler.postDelayed(callback, delay); }
            @Override public void remove(Runnable callback) { handler.removeCallbacks(callback); }
        }, message -> { android.util.Log.i("NexusWifiStartup", message); report.accept(message); });
    }

    static boolean supported() { return Build.VERSION.SDK_INT < 29; }
    boolean enabled() { return settings.getBoolean(PREFERENCE, supported()); }
    void enabled(boolean enabled) {
        settings.edit().putBoolean(PREFERENCE, enabled).apply();
        if (enabled) retry(); else session.restart(false, supported());
    }
    void start() { session.start(enabled(), supported()); }
    void retry() { session.restart(enabled(), supported()); }
    void stop() { session.stop(); }
    String status() { return session.status(); }
}
