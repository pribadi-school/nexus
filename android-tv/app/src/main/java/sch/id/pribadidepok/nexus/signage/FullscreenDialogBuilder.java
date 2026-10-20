package sch.id.pribadidepok.nexus.signage;

import android.app.AlertDialog;
import android.content.Context;
import android.view.Window;
import android.view.WindowManager;

/** Native player menus inherit immersive mode instead of exposing the nav bar. */
final class FullscreenDialogBuilder extends AlertDialog.Builder {
    FullscreenDialogBuilder(Context context) { super(context); }

    @Override public AlertDialog show() {
        AlertDialog dialog = create();
        Window window = dialog.getWindow();
        if (window == null) { dialog.show(); return dialog; }
        // Apply the flags before a focused dialog can reset the activity's bars.
        window.addFlags(WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE);
        Fullscreen fullscreen = new Fullscreen(window);
        fullscreen.start();
        dialog.show();
        window.clearFlags(WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE);
        fullscreen.focusReturned();
        dialog.setOnDismissListener(ignored -> fullscreen.stop());
        return dialog;
    }
}
