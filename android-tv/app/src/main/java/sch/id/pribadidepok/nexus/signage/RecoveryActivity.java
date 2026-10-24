package sch.id.pribadidepok.nexus.signage;

import android.app.Activity;
import android.content.ComponentName;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.WindowManager;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;

/** Independent local maintenance process: no Wi-Fi, network or player initialization. */
public final class RecoveryActivity extends Activity {
    private final Handler ui = new Handler(Looper.getMainLooper());
    private TextView status;
    private SettingsLaunchSession launchSession;
    private Intent[] launchTargets;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().clearFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN);
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_VISIBLE);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        // Explicit service stop is temporary: returning to MainActivity starts
        // the keeper again if its existing user preference is enabled.
        String pauseStatus = "Wi-Fi keeper paused for maintenance. Return to Nexus to resume it.";
        try { stopService(new Intent(this, WifiKeeperService.class)); }
        catch (RuntimeException blocked) { pauseStatus = "Android blocked pausing the Wi-Fi keeper. You can still try the Settings buttons."; }
        LinearLayout form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL); form.setPadding(dp(24), dp(20), dp(24), dp(20));
        form.setBackgroundColor(getColor(R.color.nexus_navy));
        form.addView(label("Nexus network & recovery", 28));
        form.addView(label("Works without internet or pairing. If an Android screen freezes, press Back or Home. Apps and Google TV app settings provide other recovery routes.", 17));
        status = label(pauseStatus, 17); form.addView(status);
        launchSession = new SettingsLaunchSession(index -> {
            try { startActivity(launchTargets[index]); }
            catch (RuntimeException blocked) {
                android.util.Log.w("NexusRecovery", "Settings launch failed: " + launchTargets[index], blocked);
                throw blocked;
            }
        }, new SettingsLaunchSession.Timer() {
            @Override public void post(Runnable callback, long delay) { ui.postDelayed(callback, delay); }
            @Override public void remove(Runnable callback) { ui.removeCallbacks(callback); }
        }, status::setText);
        Button wifi = button("Open Android Wi-Fi settings", () -> launch("Wi-Fi settings", new Intent[]{
            new Intent().setComponent(new ComponentName("com.android.tv.settings", "com.android.tv.settings.connectivity.NetworkActivity")),
            new Intent(android.provider.Settings.ACTION_WIFI_SETTINGS),
            new Intent(android.provider.Settings.ACTION_WIRELESS_SETTINGS)}));
        form.addView(wifi);
        Button system = button("Open Android Settings", () -> launch("Android Settings", new Intent[]{
            new Intent().setComponent(new ComponentName("com.android.tv.settings", "com.android.tv.settings.MainSettings")),
            new Intent(android.provider.Settings.ACTION_SETTINGS)}));
        form.addView(system);
        form.addView(button("Open Apps settings", () -> launch("Apps settings", new Intent[]{
            new Intent().setComponent(new ComponentName("com.android.tv.settings", "com.android.tv.settings.device.apps.AppsActivity")),
            new Intent(android.provider.Settings.ACTION_MANAGE_APPLICATIONS_SETTINGS)})));
        form.addView(button("Open Google TV app settings", () -> launch("Google TV app settings", new Intent[]{
            new Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:com.google.android.tvlauncher")),
            new Intent(android.provider.Settings.ACTION_MANAGE_APPLICATIONS_SETTINGS)})));
        form.addView(button("Return to Nexus", this::returnToNexus));
        ScrollView scroll = new ScrollView(this); scroll.addView(form); setContentView(scroll);
        (getIntent().getBooleanExtra("prefer_wifi", true) ? wifi : system).requestFocus();
    }

    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }
    private TextView label(String value, int size) {
        TextView view = new TextView(this); view.setText(value); view.setTextSize(size);
        view.setTextColor(Color.WHITE); view.setPadding(0, dp(6), 0, dp(10)); return view;
    }
    private Button button(String label, Runnable action) {
        Button button = new Button(this); button.setText(label); button.setAllCaps(false); button.setTextSize(18);
        button.setMinHeight(dp(52)); button.setOnClickListener(view -> action.run()); return button;
    }

    private void launch(String label, Intent[] candidates) {
        if (launchSession.running()) return;
        launchTargets = candidates;
        launchSession.start(label, candidates.length);
    }

    private void returnToNexus() {
        startActivity(new Intent(this, MainActivity.class).addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_NEW_TASK));
        finish();
    }
    @Override protected void onResume() {
        super.onResume(); launchSession.resumed();
    }
    @Override protected void onPause() {
        launchSession.paused(); super.onPause();
    }
    @Override protected void onDestroy() { launchSession.cancel(); ui.removeCallbacksAndMessages(null); super.onDestroy(); }
}
