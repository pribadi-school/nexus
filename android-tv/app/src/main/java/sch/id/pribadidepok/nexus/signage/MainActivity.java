package sch.id.pribadidepok.nexus.signage;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.res.ColorStateList;
import android.graphics.Color;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.graphics.drawable.StateListDrawable;
import android.net.ConnectivityManager;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.Gravity;
import android.view.KeyEvent;
import android.view.View;
import android.view.WindowManager;
import android.webkit.CookieManager;
import android.webkit.SslErrorHandler;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.ImageView;
import androidx.media3.common.MediaItem;
import androidx.media3.common.PlaybackException;
import androidx.media3.common.Player;
import androidx.media3.exoplayer.ExoPlayer;
import androidx.media3.ui.AspectRatioFrameLayout;
import androidx.media3.ui.PlayerView;
import org.json.JSONArray;
import org.json.JSONObject;
import java.security.SecureRandom;
import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.Executors;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.ScheduledFuture;
import java.util.concurrent.TimeUnit;

/** Native TV player; Nexus is the management server, not a playback dependency. */
public final class MainActivity extends Activity {
    private static final String DEFAULT_SERVER = "https://nexus.pribadidepok.sch.id";
    private final Handler ui = new Handler(Looper.getMainLooper());
    private final ScheduledExecutorService network = Executors.newSingleThreadScheduledExecutor();
    private final ExecutorService downloader = Executors.newSingleThreadExecutor();
    private SharedPreferences settings;
    private MediaCache cache;
    private FrameLayout stage, media;
    private LinearLayout message, controls;
    private TextView headline, explanation, status;
    private ExoPlayer player;
    private WebView website;
    private Bitmap imageBitmap;
    private boolean imageLoading;
    private volatile JSONObject active;
    private ScheduledFuture<?> pollTask;
    private volatile boolean foreground, downloading, remoteEnabled = true;
    private volatile long epoch;
    private volatile String pairingCode, wantedRevision = "", lastError = "";
    private volatile int current = -1;
    private int failures;
    private long playbackGeneration;
    private Runnable advance, hideControls;

    @Override public void onCreate(Bundle state) {
        super.onCreate(state);
        getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
        settings = getSharedPreferences("nexus", MODE_PRIVATE);
        cache = new MediaCache(this);
        createStage();
        if (token().isEmpty()) setup("Connect this TV to Nexus."); else loadSaved();
    }

    private int navy() { return getColor(R.color.nexus_navy); }
    private int muted() { return getColor(R.color.on_navy_muted); }
    private int dp(int value) { return Math.round(value * getResources().getDisplayMetrics().density); }
    private TextView text(String value, int size, boolean bold) {
        TextView view = new TextView(this); view.setText(value); view.setTextSize(size); view.setTextColor(Color.WHITE);
        if (bold) view.setTypeface(null, Typeface.BOLD);
        return view;
    }
    private GradientDrawable background(int fill, int border) {
        GradientDrawable value = new GradientDrawable(); value.setColor(fill); value.setCornerRadius(dp(8)); value.setStroke(dp(2), border); return value;
    }
    private Button button(String label, Runnable action) {
        Button value = new Button(this); value.setText(label); value.setTextSize(18); value.setAllCaps(false);
        value.setMinHeight(dp(52)); value.setPadding(dp(20), dp(10), dp(20), dp(10));
        StateListDrawable backgrounds = new StateListDrawable();
        backgrounds.addState(new int[]{android.R.attr.state_focused}, background(Color.WHITE, Color.WHITE));
        backgrounds.addState(new int[]{android.R.attr.state_pressed}, background(Color.WHITE, Color.WHITE));
        backgrounds.addState(new int[]{}, background(getColor(R.color.nexus_blue), getColor(R.color.on_navy_muted)));
        value.setBackground(backgrounds);
        value.setTextColor(new ColorStateList(new int[][]{new int[]{android.R.attr.state_focused}, new int[]{android.R.attr.state_pressed}, new int[]{}}, new int[]{navy(), navy(), Color.WHITE}));
        value.setOnClickListener(view -> action.run());
        return value;
    }
    private void createStage() {
        stage = new FrameLayout(this); stage.setBackgroundColor(navy()); setContentView(stage);
        media = new FrameLayout(this); stage.addView(media, new FrameLayout.LayoutParams(-1, -1));
        message = new LinearLayout(this); message.setOrientation(LinearLayout.VERTICAL); message.setGravity(Gravity.CENTER);
        message.setPadding(dp(60), dp(32), dp(60), dp(32)); message.setBackgroundColor(navy());
        headline = text("Nexus Signage", 36, true); headline.setGravity(Gravity.CENTER); message.addView(headline);
        explanation = text("", 22, false); explanation.setTextColor(muted()); explanation.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams copy = new LinearLayout.LayoutParams(-1, -2); copy.topMargin = dp(20); message.addView(explanation, copy);
        stage.addView(message, new FrameLayout.LayoutParams(-1, -1));
        controls = new LinearLayout(this); controls.setOrientation(LinearLayout.VERTICAL); controls.setPadding(dp(32), dp(20), dp(32), dp(20)); controls.setBackgroundColor(navy());
        status = text("", 18, false); status.setTextColor(muted()); controls.addView(status);
        LinearLayout row = new LinearLayout(this); row.setGravity(Gravity.CENTER_VERTICAL); row.setPadding(0, dp(12), 0, 0);
        for (Button control : new Button[]{button("Resume", () -> controls.setVisibility(View.GONE)), button("Next", () -> play(current + 1)), button("Sync now", () -> schedule(0)), button("Startup", this::startup), button("Settings", this::options)}) {
            LinearLayout.LayoutParams layout = new LinearLayout.LayoutParams(-2, -2); layout.setMarginEnd(dp(12)); row.addView(control, layout);
        }
        controls.addView(row); FrameLayout.LayoutParams bottom = new FrameLayout.LayoutParams(-1, -2, Gravity.BOTTOM); stage.addView(controls, bottom); controls.setVisibility(View.GONE);
        stage.setFocusable(true); stage.setFocusableInTouchMode(true);
    }
    private String base() { return settings.getString("server", DEFAULT_SERVER); }
    private String token() { return settings.getString("token", ""); }
    private String secret() { return settings.getString("device_key", ""); }
    private String randomKey() {
        byte[] bytes = new byte[32]; new SecureRandom().nextBytes(bytes);
        StringBuilder result = new StringBuilder(); for (byte value : bytes) result.append(String.format(java.util.Locale.ROOT, "%02x", value & 255)); return result.toString();
    }
    private void show(String title, String detail) { headline.setText(title); explanation.setText(detail); message.setVisibility(View.VISIBLE); }
    private void note(String detail) { status.setText(detail); }
    private void setup(String detail) {
        releaseMedia(); active = null; current = -1; pairingCode = null; controls.setVisibility(View.GONE); message.setVisibility(View.GONE);
        ScrollView scroll = new ScrollView(this); LinearLayout form = new LinearLayout(this); form.setOrientation(LinearLayout.VERTICAL);
        form.setPadding(dp(64), dp(36), dp(64), dp(36)); form.setGravity(Gravity.CENTER_VERTICAL); form.setBackgroundColor(navy());
        form.addView(text("Nexus Signage", 36, true)); TextView intro = text(detail, 22, false); intro.setTextColor(muted()); intro.setPadding(0, dp(16), 0, dp(16)); form.addView(intro);
        form.addView(text("Nexus server address", 20, false)); EditText server = new EditText(this); server.setText(base()); server.setSingleLine(true); server.setTextSize(22); server.setTextColor(Color.WHITE); server.setSelectAllOnFocus(true); server.setInputType(android.text.InputType.TYPE_CLASS_TEXT | android.text.InputType.TYPE_TEXT_VARIATION_URI); form.addView(server, new LinearLayout.LayoutParams(-1, dp(64)));
        TextView help = text("Use your school's HTTPS address. For a local test, use the computer's LAN IP; localhost refers to this TV.", 18, false); help.setTextColor(muted()); help.setPadding(0, dp(12), 0, dp(20)); form.addView(help);
        Button connect = button("Get pairing code", () -> {
            try {
                String value = UrlPolicy.server(server.getText().toString());
                settings.edit().putString("server", value).putString("device_key", randomKey()).remove("token").putBoolean("paused", false).apply();
                epoch++; stage.removeView(scroll); show("Connecting to Nexus", "Requesting a pairing code…");
                long attempt = epoch; network.execute(() -> startPairing(attempt));
            } catch (IllegalArgumentException exception) { intro.setText(exception.getMessage()); server.requestFocus(); }
        });
        form.addView(connect); Button boot = button("Set up automatic startup", this::startup); LinearLayout.LayoutParams bootLayout = new LinearLayout.LayoutParams(-1, -2); bootLayout.topMargin = dp(16); form.addView(boot, bootLayout);
        scroll.addView(form); stage.addView(scroll, new FrameLayout.LayoutParams(-1, -1)); server.requestFocus();
    }
    private void startPairing(long attempt) {
        try {
            NexusApi api = new NexusApi(base()); Map<String, String> fields = new HashMap<>(); fields.put("device_key", secret()); fields.put("device_name", Build.MANUFACTURER + " " + Build.MODEL);
            JSONObject reply = api.post("/signage/api/pairing/start", fields); String code = reply.getString("code");
            if (!code.matches("[0-9]{6}")) throw new IllegalArgumentException("The server returned an invalid pairing code.");
            ui.post(() -> { if (attempt != epoch) return; pairingCode = code; headline.setTextSize(64); show(code, "On your computer, open Nexus → Signage → Pair Android TV.\nEnter this code and choose this TV's screen. The code expires in 10 minutes."); schedule(1); });
        } catch (Exception exception) { ui.post(() -> { if (attempt == epoch) setup("Could not connect. " + friendly(exception)); }); }
    }
    private synchronized void schedule(int seconds) {
        if (!foreground || network.isShutdown()) return;
        if (pollTask != null) pollTask.cancel(false);
        pollTask = network.schedule(this::tick, seconds, TimeUnit.SECONDS);
    }
    private void tick() {
        long attempt = epoch;
        try {
            if (!foreground) return;
            if (token().isEmpty()) {
                if (pairingCode == null) return;
                Map<String, String> fields = new HashMap<>(); fields.put("device_key", secret()); fields.put("code", pairingingCode());
                JSONObject reply = new NexusApi(base()).post("/signage/api/pairing/poll", fields);
                if ("paired".equals(reply.optString("status"))) {
                    String pairedToken = reply.getString("player_token"); if (!pairedToken.matches("[a-f0-9]{64}")) throw new IllegalArgumentException("Invalid player key.");
                    ui.post(() -> { if (attempt != epoch) return; pairingCode = null; headline.setTextSize(36); settings.edit().putString("token", pairedToken).putBoolean("paused", false).apply(); show("TV paired", "Downloading the assigned playlist…"); schedule(0); });
                }
            } else {
                String endpoint = "/signage/player/" + token() + "/manifest";
                JSONObject entry = currentItem();
                endpoint += "?error=" + NexusApi.encode(lastError);
                if (entry != null) endpoint += "&item=" + entry.optInt("id");
                JSONObject data = new NexusApi(base()).get(endpoint);
                if (!data.has("screen") || !data.has("items") || !data.has("revision")) throw new IllegalArgumentException("Nexus returned an invalid playlist.");
                boolean enabled = data.getJSONObject("screen").optBoolean("enabled", true); remoteEnabled = enabled;
                settings.edit().putBoolean("paused", !enabled).apply();
                if (!enabled) { ui.post(() -> { if (attempt == epoch) { releaseMedia(); show("Screen paused", "Resume this screen from Nexus."); } }); return; }
                ui.post(() -> { if (attempt == epoch) accept(data, attempt); });
            }
        } catch (NexusApi.Failure failure) {
            if (attempt != epoch) return;
            if (!token().isEmpty() && (failure.status == 401 || failure.status == 403 || failure.status == 404)) ui.post(() -> { if (attempt == epoch) revoke(); });
            else if (token().isEmpty() && failure.status == 410) ui.post(() -> { if (attempt != epoch) return; pairingCode = null; headline.setTextSize(36); setup("That pairing code expired. Request a new code to continue."); });
            else ui.post(() -> { if (attempt == epoch) disconnected(friendly(failure)); });
        } catch (Exception exception) { if (attempt == epoch) ui.post(() -> disconnected(friendly(exception))); }
        finally { schedule(token().isEmpty() ? 5 : 30); }
    }
    private String pairingingCode() { return pairingCode == null ? "" : pairingCode; }
    private String friendly(Exception exception) { return exception instanceof NexusApi.Failure || exception instanceof IllegalArgumentException || exception instanceof java.io.IOException ? (exception.getMessage() == null ? "Check the network connection." : exception.getMessage()) : "Check the server address and network connection."; }
    private void disconnected(String reason) {
        note("Offline · " + reason + " Downloaded videos will continue.");
        if (active == null && !token().isEmpty()) show("Waiting for a connection", "Connect this TV to Nexus to download its first playlist. " + reason);
    }
    private void accept(JSONObject data, long attempt) {
        if (active != null && data.optString("revision").equals(active.optString("revision"))) {
            note(data.optJSONObject("screen").optString("name", "Nexus Signage") + " · Connected");
            if (player == null && website == null && imageBitmap == null && !imageLoading) play(current < 0 ? 0 : current); return;
        }
        wantedRevision = data.optString("revision");
        if (downloading) return;
        downloading = true;
        String revision = wantedRevision; String server = base();
        downloader.execute(() -> {
            try {
                JSONObject prepared = cache.prepare(new NexusApi(server), data, () -> !foreground || attempt != epoch || !remoteEnabled || !revision.equals(wantedRevision), detail -> ui.post(() -> { if (attempt == epoch) { note(detail); if (active == null) show("Downloading your playlist", detail); } }));
                ui.post(() -> {
                    if (attempt != epoch || !remoteEnabled || !revision.equals(wantedRevision)) return;
                    try { cache.save(prepared); } catch (java.io.IOException error) { disconnected(error.getMessage()); return; }
                    releaseMedia(); active = prepared; current = -1; failures = 0; note(prepared.optJSONObject("screen").optString("name", "Nexus Signage") + " · Playlist downloaded");
                    play(0); downloader.execute(() -> { if (attempt == epoch) cache.prune(server, prepared); });
                });
            } catch (NexusApi.Failure failure) { ui.post(() -> { if (attempt == epoch) { if (failure.status == 401 || failure.status == 403) revoke(); else disconnected(friendly(failure)); } }); }
            catch (Exception exception) { ui.post(() -> { if (attempt == epoch && remoteEnabled && revision.equals(wantedRevision)) { lastError = friendly(exception); disconnected(lastError); } }); }
            finally { downloading = false; }
        });
    }
    private void loadSaved() {
        remoteEnabled = !settings.getBoolean("paused", false);
        active = cache.read(base());
        if (!remoteEnabled) show("Screen paused", "Resume this screen from Nexus.");
        else if (active != null) play(0);
        else show("Preparing this screen", "Connecting to Nexus and downloading its playlist…");
    }
    private JSONObject currentItem() {
        JSONArray items = active == null ? null : active.optJSONArray("items");
        return items != null && current >= 0 && current < items.length() ? items.optJSONObject(current) : null;
    }
    private void releaseMedia() {
        playbackGeneration++;
        imageLoading = false;
        if (advance != null) ui.removeCallbacks(advance);
        advance = null;
        if (player != null) { player.release(); player = null; }
        if (website != null) { website.stopLoading(); website.loadUrl("about:blank"); website.destroy(); website = null; }
        media.removeAllViews();
        if (imageBitmap != null) { imageBitmap.recycle(); imageBitmap = null; }
    }
    private boolean connected() {
        ConnectivityManager manager = getSystemService(ConnectivityManager.class);
        NetworkCapabilities capabilities = manager == null ? null : manager.getNetworkCapabilities(manager.getActiveNetwork());
        return capabilities != null && capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
    }
    @androidx.annotation.OptIn(markerClass = androidx.media3.common.util.UnstableApi.class)
    private void play(int index) {
        releaseMedia();
        if (!remoteEnabled) { show("Screen paused", "Resume this screen from Nexus."); return; }
        JSONArray items = active == null ? null : active.optJSONArray("items");
        if (items == null || items.length() == 0) { show("Waiting for content", "Assign a playlist and add content in Nexus."); return; }
        current = ((index % items.length()) + items.length()) % items.length();
        JSONObject item = items.optJSONObject(current);
        int attempts = 0;
        while (item != null && "website".equals(item.optString("type")) && !connected() && attempts++ < items.length()) {
            current = (current + 1) % items.length(); item = items.optJSONObject(current);
        }
        if (item == null || ("website".equals(item.optString("type")) && !connected())) { show("Website content needs internet", "Reconnect this TV. Downloaded MP4 videos can play offline."); return; }
        long generation = playbackGeneration; JSONObject screen = active.optJSONObject("screen");
        media.setRotation("portrait".equals(screen.optString("orientation")) ? 90 : 0);
        media.post(() -> {
            if (generation != playbackGeneration) return;
            boolean portrait = "portrait".equals(screen.optString("orientation"));
            FrameLayout.LayoutParams layout = new FrameLayout.LayoutParams(portrait ? stage.getHeight() : -1, portrait ? stage.getWidth() : -1, Gravity.CENTER); media.setLayoutParams(layout);
        });
        message.setVisibility(View.GONE);
        if ("video".equals(item.optString("type"))) {
            java.io.File local = cache.media(base(), item);
            if (!local.isFile()) { failedItem("A downloaded video is missing.", generation); return; }
            PlayerView view = new PlayerView(this); view.setUseController(false); view.setFocusable(false); view.setResizeMode("cover".equals(screen.optString("fit")) ? AspectRatioFrameLayout.RESIZE_MODE_ZOOM : AspectRatioFrameLayout.RESIZE_MODE_FIT);
            media.addView(view, new FrameLayout.LayoutParams(-1, -1)); player = new ExoPlayer.Builder(this).build(); view.setPlayer(player);
            player.setVolume(screen.optBoolean("muted", true) ? 0f : 1f);
            player.setRepeatMode(Player.REPEAT_MODE_ONE);
            long videoDuration = Math.max(5, Math.min(86400, item.optInt("duration_seconds", 30))) * 1000L;
            player.addListener(new Player.Listener() {
                @Override public void onPlaybackStateChanged(int state) { if (generation != playbackGeneration) return; if (state == Player.STATE_READY) { failures = 0; lastError = ""; } }
                @Override public void onIsPlayingChanged(boolean isPlaying) {
                    if (generation != playbackGeneration || !isPlaying || advance != null) return;
                    advance = () -> { if (generation == playbackGeneration) play(current + 1); };
                    ui.postDelayed(advance, videoDuration);
                }
                @Override public void onPlayerError(PlaybackException error) { failedItem("This TV could not decode a video. Use H.264 MP4 with AAC audio.", generation); }
            });
            player.setMediaItem(MediaItem.fromUri(Uri.fromFile(local))); player.prepare(); player.play();
        } else if ("image".equals(item.optString("type"))) {
            imageLoading = true;
            java.io.File local = cache.media(base(), item);
            String imageName = item.optString("name");
            long imageDuration = Math.max(5, Math.min(86400, item.optInt("duration_seconds", 30))) * 1000L;
            int targetWidth = Math.max(1, getResources().getDisplayMetrics().widthPixels);
            int targetHeight = Math.max(1, getResources().getDisplayMetrics().heightPixels);
            downloader.execute(() -> {
                Bitmap bitmap = null;
                try {
                    BitmapFactory.Options options = new BitmapFactory.Options(); options.inJustDecodeBounds = true;
                    BitmapFactory.decodeFile(local.getPath(), options);
                    // Bound image allocations to approximately the TV resolution.
                    options.inSampleSize = 1;
                    while (options.outWidth / options.inSampleSize > targetWidth * 2 || options.outHeight / options.inSampleSize > targetHeight * 2) options.inSampleSize *= 2;
                    options.inJustDecodeBounds = false;
                    bitmap = BitmapFactory.decodeFile(local.getPath(), options);
                } catch (RuntimeException | OutOfMemoryError ignored) { /* Report through the normal item failure path. */ }
                Bitmap decoded = bitmap;
                ui.post(() -> {
                    if (generation != playbackGeneration || !foreground) { if (decoded != null) decoded.recycle(); return; }
                    imageLoading = false;
                    if (decoded == null) { failedItem("This image could not load.", generation); return; }
                    imageBitmap = decoded;
                    ImageView view = new ImageView(this); view.setImageBitmap(decoded);
                    view.setContentDescription(imageName);
                    view.setScaleType("cover".equals(screen.optString("fit")) ? ImageView.ScaleType.CENTER_CROP : ImageView.ScaleType.FIT_CENTER);
                    media.addView(view, new FrameLayout.LayoutParams(-1, -1)); failures = 0; lastError = "";
                    advance = () -> { if (generation == playbackGeneration) play(current + 1); };
                    ui.postDelayed(advance, imageDuration);
                });
            });
        } else if ("website".equals(item.optString("type")) && UrlPolicy.website(item.optString("url"))) {
            website = new WebView(this); WebSettings web = website.getSettings(); web.setJavaScriptEnabled(true); web.setDomStorageEnabled(true); web.setAllowFileAccess(false); web.setAllowContentAccess(false); web.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW); web.setMediaPlaybackRequiresUserGesture(false); web.setSupportMultipleWindows(false);
            CookieManager.getInstance().setAcceptCookie(false); CookieManager.getInstance().setAcceptThirdPartyCookies(website, false);
            website.setWebViewClient(new WebViewClient() {
                @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) { return !UrlPolicy.website(request.getUrl().toString()); }
                @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) { handler.cancel(); failedItem("The website has a certificate error.", generation); }
                @Override public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) { if (request.isForMainFrame()) failedItem("The website could not load.", generation); }
            });
            media.addView(website, new FrameLayout.LayoutParams(-1, -1)); website.loadUrl(item.optString("url"));
            advance = () -> { if (generation == playbackGeneration) play(current + 1); }; ui.postDelayed(advance, Math.max(5, Math.min(86400, item.optInt("duration_seconds", 30))) * 1000L);
        } else failedItem("This playlist item is not supported.", generation);
    }
    private void failedItem(String reason, long generation) {
        if (generation != playbackGeneration) return;
        lastError = reason; failures++; releaseMedia(); show("Content could not play", reason);
        int count = active == null ? 0 : active.optJSONArray("items").length();
        advance = () -> play(current + 1); ui.postDelayed(advance, failures >= count ? 30000 : 3000);
    }
    private void revoke() {
        epoch++; wantedRevision = ""; remoteEnabled = false; releaseMedia(); active = null; settings.edit().remove("token").remove("device_key").putBoolean("paused", false).apply();
        downloader.execute(cache::clear); setup("This TV's player link was revoked or its screen was deleted. Pair it again to continue.");
    }
    private void startup() {
        new AlertDialog.Builder(this).setTitle("Run automatically when the TV starts")
            .setMessage(Startup.isHome(this) ? "Nexus is the default Home app. Android will open it at startup and return to playback after standby. Use System settings to change the default later."
                : "Choose Nexus Signage as the TV's default Home app for reliable startup. This is a one-time setup. Some TV manufacturers require an administrator or ADB to change the Home app.")
            .setPositiveButton(Startup.isHome(this) ? "Open Home settings" : "Choose Nexus as Home", (dialog, which) -> {
                try { Startup.configure(this); } catch (RuntimeException exception) { show("TV startup settings", "This TV does not expose Home-app selection. Ask your IT administrator to set Nexus Signage as the default launcher using ADB; see the Nexus Android TV setup guide."); }
            }).setNegativeButton("Cancel", null).show();
    }
    private void options() {
        new AlertDialog.Builder(this).setTitle("Nexus Signage settings")
            .setItems(new String[]{"Automatic startup", "Change server / pair again", "System settings"}, (dialog, which) -> {
                if (which == 0) startup();
                else if (which == 1) new AlertDialog.Builder(this).setTitle("Disconnect this TV?").setMessage("This clears its player key and downloaded playlist. You will need to pair it again.").setPositiveButton("Disconnect", (d, w) -> revoke()).setNegativeButton("Cancel", null).show();
                else { try { startActivity(new Intent(android.provider.Settings.ACTION_SETTINGS)); } catch (RuntimeException ignored) {} }
            }).setNegativeButton("Close", null).show();
    }
    private void revealControls() {
        controls.setVisibility(View.VISIBLE); ((LinearLayout) controls.getChildAt(1)).getChildAt(0).requestFocus();
        if (hideControls != null) ui.removeCallbacks(hideControls);
        hideControls = () -> { controls.setVisibility(View.GONE); stage.requestFocus(); }; ui.postDelayed(hideControls, 15000);
    }
    @Override public boolean dispatchKeyEvent(KeyEvent event) {
        if (event.getAction() == KeyEvent.ACTION_DOWN && !token().isEmpty()) {
            if (event.getKeyCode() == KeyEvent.KEYCODE_DPAD_CENTER || event.getKeyCode() == KeyEvent.KEYCODE_MENU) {
                if (controls.getVisibility() != View.VISIBLE) { revealControls(); return true; }
            }
            if (event.getKeyCode() == KeyEvent.KEYCODE_MEDIA_NEXT && active != null) { play(current + 1); return true; }
        }
        return super.dispatchKeyEvent(event);
    }
    // This remote-controlled TV activity explicitly opts out of predictive
    // gesture callbacks in the manifest, retaining its physical Back handler.
    @android.annotation.SuppressLint("GestureBackNavigation")
    @Override public void onBackPressed() {
        if (!token().isEmpty() && controls.getVisibility() != View.VISIBLE) revealControls(); else super.onBackPressed();
    }
    @Override protected void onResume() {
        super.onResume(); foreground = true;
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION | View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY);
        if (!token().isEmpty() && active != null && player == null && website == null && imageBitmap == null && !imageLoading && remoteEnabled) play(current < 0 ? 0 : current);
        schedule(0);
    }
    @Override protected void onPause() { foreground = false; synchronized (this) { if (pollTask != null) pollTask.cancel(false); } releaseMedia(); super.onPause(); }
    @Override protected void onDestroy() { foreground = false; epoch++; ui.removeCallbacksAndMessages(null); network.shutdownNow(); downloader.shutdownNow(); releaseMedia(); super.onDestroy(); }
}
