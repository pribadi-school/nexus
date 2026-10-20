package sch.id.pribadidepok.nexus.signage;

import android.os.Build;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.view.Window;
import android.view.WindowInsets;
import android.view.WindowInsetsController;
import android.view.WindowManager;

/** Event-driven immersive playback, including Android 7's three-button bar. */
final class Fullscreen {
    private final Window window;
    private final Handler ui = new Handler(Looper.getMainLooper());
    private final Runnable restore = this::restoreFocusedWindow;
    private boolean active;

    Fullscreen(Window window) { this.window = window; }

    void start() {
        active = true;
        window.getDecorView().setOnSystemUiVisibilityChangeListener(visibility -> {
            int hidden = View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION;
            if (active && (visibility & hidden) != hidden && window.getDecorView().hasWindowFocus()) restoreSoon();
        });
        apply();
        restoreSoon();
    }

    void stop() { active = false; ui.removeCallbacks(restore); }

    void focusReturned() {
        if (active) { apply(); restoreSoon(); }
    }

    private void restoreSoon() {
        ui.removeCallbacks(restore);
        ui.postDelayed(restore, 1200);
    }

    private void restoreFocusedWindow() {
        if (window.getDecorView().hasWindowFocus()) apply();
    }

    private void apply() {
        if (!active) return;
        window.addFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN);
        // Layout flags keep playback at full size during temporary system overlays.
        window.getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY
            | View.SYSTEM_UI_FLAG_FULLSCREEN | View.SYSTEM_UI_FLAG_HIDE_NAVIGATION
            | View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION
            | View.SYSTEM_UI_FLAG_LAYOUT_STABLE);
        if (Build.VERSION.SDK_INT >= 30) {
            window.setDecorFitsSystemWindows(false);
            WindowInsetsController controller = window.getInsetsController();
            if (controller != null) {
                controller.setSystemBarsBehavior(WindowInsetsController.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE);
                controller.hide(WindowInsets.Type.systemBars());
            }
        }
    }
}
