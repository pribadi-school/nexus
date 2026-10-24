package sch.id.pribadidepok.nexus.signage;

import android.content.Context;
import android.content.SharedPreferences;
import android.content.Intent;
import android.os.Build;
import java.util.function.Consumer;

/** UI facade for the single application Wi-Fi keeper, independent of Activity lifetime. */
final class WifiStartup {
    static final String PREFERENCE = "wifi_startup";
    static final String STATUS = "wifi_keeper_status";
    private final Context app;
    private final SharedPreferences settings;
    private final Consumer<String> report;
    private final SharedPreferences.OnSharedPreferenceChangeListener listener;
    private boolean listening;

    WifiStartup(Context context, Consumer<String> report) {
        app = context.getApplicationContext();
        settings = preferences(app);
        this.report = report;
        listener = (preferences, key) -> { if (STATUS.equals(key)) this.report.accept(status()); };
    }

    static boolean supported(Context context) {
        return WifiRecoveryPolicy.supported(Build.VERSION.SDK_INT, context.getApplicationInfo().targetSdkVersion);
    }
    static SharedPreferences preferences(Context context) { return context.getSharedPreferences("nexus", Context.MODE_PRIVATE); }
    static boolean enabled(Context context) { return supported(context) && preferences(context).getBoolean(PREFERENCE, true); }
    static void publish(Context context, String detail) {
        SharedPreferences preferences = preferences(context);
        if (!detail.equals(preferences.getString(STATUS, ""))) {
            preferences.edit().putString(STATUS, detail).apply();
            android.util.Log.i("NexusWifiKeeper", detail);
        }
    }

    /** Safe from boot and foreground entry; the service owns all radio work. */
    static void ensureStarted(Context context) { launch(context.getApplicationContext(), false); }

    private static void launch(Context app, boolean retry) {
        if (!supported(app)) return;
        if (!enabled(app)) {
            stopService(app);
            publish(app, "Automatic Wi-Fi keeper is off.");
            return;
        }
        Intent keeper = new Intent(app, WifiKeeperService.class);
        if (retry) keeper.setAction(WifiKeeperService.ACTION_RETRY);
        try {
            if (Build.VERSION.SDK_INT >= 26) app.startForegroundService(keeper); else app.startService(keeper);
        } catch (RuntimeException blocked) {
            android.util.Log.e("NexusWifiKeeper", "Could not start Wi-Fi keeper", blocked);
            publish(app, "Android blocked the Wi-Fi keeper. Open Nexus and check this display's startup settings.");
        }
    }

    private static void stopService(Context app) {
        try { app.stopService(new Intent(app, WifiKeeperService.class)); }
        catch (RuntimeException blocked) { android.util.Log.e("NexusWifiKeeper", "Could not stop Wi-Fi keeper; disabled preference prevents further requests", blocked); }
    }

    boolean enabled() { return enabled(app); }
    void enabled(boolean enabled) {
        settings.edit().putBoolean(PREFERENCE, enabled && supported(app)).apply();
        if (enabled && supported(app)) retry();
        else {
            stopService(app);
            publish(app, "Automatic Wi-Fi keeper is off.");
        }
    }
    void start() {
        if (!listening) { settings.registerOnSharedPreferenceChangeListener(listener); listening = true; }
        report.accept(status());
        ensureStarted(app);
    }
    void retry() { launch(app, true); }
    /** Activity pause only detaches its view; unattended recovery continues. */
    void stop() { if (listening) { settings.unregisterOnSharedPreferenceChangeListener(listener); listening = false; } }
    String status() {
        if (!supported(app)) return "This build requires Wi-Fi to be enabled in System settings on this Android version.";
        if (!enabled()) return "Automatic Wi-Fi keeper is off.";
        return settings.getString(STATUS, "Starting automatic Wi-Fi recovery…");
    }
}
