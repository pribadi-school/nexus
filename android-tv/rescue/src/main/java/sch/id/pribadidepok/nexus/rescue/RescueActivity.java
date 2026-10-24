package sch.id.pribadidepok.nexus.rescue;

import android.app.Activity;
import android.content.Intent;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.wifi.WifiManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.Settings;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import java.net.Inet4Address;
import java.net.NetworkInterface;
import java.util.Collections;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** No Home role, services, boot receiver, storage access, scanning, or network credentials. */
@SuppressWarnings("deprecation")
public final class RescueActivity extends Activity {
    private final Handler ui = new Handler(Looper.getMainLooper());
    private final ExecutorService workers = Executors.newFixedThreadPool(4, task -> {
        Thread thread = new Thread(task, "NetworkRescue"); thread.setDaemon(true); return thread;
    });
    private AsyncChecks checks;
    private TextView radio, network, packages, action;
    private LinearLayout form;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        checks = new AsyncChecks(workers, new AsyncChecks.Ui() {
            @Override public void post(Runnable task, long delay) { ui.postDelayed(task, delay); }
            @Override public void remove(Runnable task) { ui.removeCallbacks(task); }
        });
        form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL); form.setPadding(dp(28), dp(18), dp(28), dp(18));
        form.setBackgroundColor(Color.rgb(15, 39, 76));
        label("Nexus Network Rescue 1.0.0", 26);
        label(Build.MANUFACTURER + " " + Build.MODEL + " | Android " + Build.VERSION.RELEASE, 18);
        label("Checks work without opening Android Settings. Wi-Fi changes happen only when you press a recovery button. Your saved networks and Nexus pairing are preserved.", 17);
        Button refresh = button("Check network again", this::refresh);
        radio = label("Wi-Fi: waiting for a check", 18);
        network = label("Network: waiting for a check", 18);
        packages = label("Android Settings: waiting for a check", 17);
        action = label("Recovery: no change requested", 18);
        button("Turn Wi-Fi ON", () -> request("Requesting Wi-Fi ON...", () -> {
            String unavailable = unavailable(); if (unavailable != null) return unavailable;
            boolean accepted = wifi().setWifiEnabled(true);
            return accepted ? "Android accepted the Wi-Fi ON request. This is not confirmation of a connection. Wait 15 seconds, then check network again."
                : "Android rejected the Wi-Fi ON request. No connection has been confirmed.";
        }));
        button("Reconnect saved Wi-Fi", () -> request("Requesting reconnection...", () -> {
            String unavailable = unavailable(); if (unavailable != null) return unavailable;
            if (wifi().getWifiState() != WifiManager.WIFI_STATE_ENABLED)
                return "Wi-Fi is not ON yet. Use Turn Wi-Fi ON, wait 15 seconds, then check again.";
            return wifi().reconnect() ? "Android accepted a reconnect request using its saved networks. Wait 15 seconds, then check network again."
                : "Android rejected the reconnect request. This tool cannot supply a missing password or repair Android's Wi-Fi service.";
        }));
        button("Close recovery tool", this::finish);
        ScrollView scroll = new ScrollView(this); scroll.addView(form); setContentView(scroll);
        refresh.requestFocus(); refresh();
    }

    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }
    private TextView label(String text, int size) {
        TextView label = new TextView(this); label.setText(text); label.setTextSize(size);
        label.setTextColor(Color.WHITE); label.setPadding(0, dp(7), 0, dp(7)); form.addView(label); return label;
    }
    private Button button(String text, Runnable task) {
        Button button = new Button(this); button.setText(text); button.setTextSize(18); button.setAllCaps(false);
        button.setMinHeight(dp(52)); button.setOnClickListener(view -> task.run()); form.addView(button); return button;
    }
    private WifiManager wifi() {
        WifiManager manager = (WifiManager) getApplicationContext().getSystemService(WIFI_SERVICE);
        if (manager == null) throw new IllegalStateException("No Android Wi-Fi service available");
        return manager;
    }
    private String unavailable() {
        if (Build.VERSION.SDK_INT < 25 || Build.VERSION.SDK_INT > 29)
            return "Manual Wi-Fi recovery in this tool supports Android 7.1 through 10 only.";
        if (Settings.Global.getInt(getContentResolver(), Settings.Global.AIRPLANE_MODE_ON, 0) != 0)
            return "Airplane mode is ON. This tool will not override it.";
        return null;
    }
    private void request(String pending, AsyncChecks.Task task) {
        if (checks.run("action", task, result -> action.setText("Recovery: " + result))) action.setText(pending);
    }
    private void check(String key, TextView view, AsyncChecks.Task task) {
        if (checks.run(key, task, result -> view.setText(key + ": " + result))) view.setText("Checking " + key + "...");
    }
    private void refresh() {
        check("Wi-Fi", radio, () -> {
            int state = wifi().getWifiState();
            String name;
            switch (state) {
                case WifiManager.WIFI_STATE_ENABLED: name = "ON (does not prove connected)"; break;
                case WifiManager.WIFI_STATE_DISABLED: name = "OFF"; break;
                case WifiManager.WIFI_STATE_ENABLING: name = "TURNING ON"; break;
                case WifiManager.WIFI_STATE_DISABLING: name = "TURNING OFF"; break;
                default: name = "UNKNOWN";
            }
            return "Wi-Fi radio: " + name;
        });
        check("Network", network, () -> {
            StringBuilder result = new StringBuilder("Local IPv4 addresses:");
            boolean found = false;
            java.util.Enumeration<NetworkInterface> interfaces = NetworkInterface.getNetworkInterfaces();
            if (interfaces != null) for (NetworkInterface item : Collections.list(interfaces)) {
                if (!item.isUp() || item.isLoopback()) continue;
                for (java.net.InetAddress address : Collections.list(item.getInetAddresses())) {
                    if (address instanceof Inet4Address && !address.isLoopbackAddress() && !address.isLinkLocalAddress()) {
                        result.append("\n").append(item.getName()).append(": ").append(address.getHostAddress()); found = true;
                    }
                }
            }
            if (!found) result.append(" none");
            // Do not make this local-address check wait for Android's Wi-Fi
            // or connectivity Binder services; they may be the broken part.
            result.append("\nAn IP address does not confirm internet access or debugging access.");
            return result.toString();
        });
        check("Apps", packages, () -> {
            StringBuilder result = new StringBuilder();
            for (String target : new String[]{"com.android.tv.settings", "com.google.android.tvlauncher"}) {
                try {
                    ApplicationInfo info = getPackageManager().getApplicationInfo(target, 0);
                    int enabled = getPackageManager().getApplicationEnabledSetting(target);
                    result.append(target).append(": ").append(info.enabled ? "enabled" : "disabled")
                        .append(" (state ").append(enabled).append(")\n");
                } catch (PackageManager.NameNotFoundException missing) { result.append(target).append(": not installed\n"); }
            }
            for (String suffix : new String[]{"MainSettings", "connectivity.NetworkActivity", "device.apps.AppsActivity"}) {
                android.content.ComponentName component = new android.content.ComponentName("com.android.tv.settings", "com.android.tv.settings." + suffix);
                try {
                    android.content.pm.ActivityInfo info = getPackageManager().getActivityInfo(component, PackageManager.MATCH_DISABLED_COMPONENTS);
                    result.append(suffix).append(": ").append(info.enabled ? "enabled" : "disabled").append("\n");
                } catch (PackageManager.NameNotFoundException missing) { result.append(suffix).append(": unavailable\n"); }
            }
            try { result.append("Nexus Signage version: ").append(getPackageManager().getPackageInfo("sch.id.pribadidepok.nexus.signage", 0).versionName).append("\n"); }
            catch (PackageManager.NameNotFoundException missing) { result.append("Nexus Signage: not installed\n"); }
            Intent home = new Intent(Intent.ACTION_MAIN).addCategory(Intent.CATEGORY_HOME);
            android.content.pm.ResolveInfo resolved = getPackageManager().resolveActivity(home, 0);
            result.append("Home: ").append(resolved == null || resolved.activityInfo == null ? "not resolved" : resolved.activityInfo.packageName);
            return result.toString();
        });
    }
    @Override protected void onDestroy() {
        checks.close(); workers.shutdownNow(); ui.removeCallbacksAndMessages(null); super.onDestroy();
    }
}
