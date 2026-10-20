package sch.id.pribadidepok.nexus.signage;

import android.app.Activity;
import android.app.role.RoleManager;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.pm.ResolveInfo;
import android.content.ActivityNotFoundException;
import android.os.Build;
import android.provider.Settings;

/** HOME is launched by Android itself, avoiding background-launch restrictions. */
public final class Startup extends BroadcastReceiver {
    static boolean isHome(Context context) {
        Intent intent = new Intent(Intent.ACTION_MAIN).addCategory(Intent.CATEGORY_HOME);
        ResolveInfo home = context.getPackageManager().resolveActivity(intent, 0);
        return home != null && home.activityInfo != null && context.getPackageName().equals(home.activityInfo.packageName);
    }
    static void configure(Activity activity) {
        if (KioskAdmin.configure(activity)) return;
        if (Build.VERSION.SDK_INT >= 29) {
            RoleManager roles = activity.getSystemService(RoleManager.class);
            if (roles != null && roles.isRoleAvailable(RoleManager.ROLE_HOME)) {
                activity.startActivityForResult(roles.createRequestRoleIntent(RoleManager.ROLE_HOME), 101);
                return;
            }
        }
        try {
            activity.startActivity(new Intent(Settings.ACTION_HOME_SETTINGS));
        } catch (ActivityNotFoundException hiddenHomeSettings) {
            activity.startActivity(new Intent(Settings.ACTION_MANAGE_DEFAULT_APPS_SETTINGS));
        }
    }
    @Override public void onReceive(Context context, Intent intent) {
        if (!Intent.ACTION_BOOT_COMPLETED.equals(intent.getAction()) && !Intent.ACTION_MY_PACKAGE_REPLACED.equals(intent.getAction())) return;
        try { KioskAdmin.configure(context); } catch (RuntimeException ignored) { /* Normal Home startup remains available. */ }
        // Modern Android starts the selected HOME app. On older TVs the boot
        // receiver also works after the first installation/setup launch.
        if (isHome(context) || Build.VERSION.SDK_INT < 29) {
            try {
                context.startActivity(new Intent(context, MainActivity.class)
                        .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP));
            } catch (RuntimeException ignored) { /* Vendor boot policy can restrict activity launches. */ }
        }
    }
}
