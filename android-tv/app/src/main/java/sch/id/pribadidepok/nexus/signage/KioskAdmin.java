package sch.id.pribadidepok.nexus.signage;

import android.app.admin.DeviceAdminReceiver;
import android.app.admin.DevicePolicyManager;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;

/** Optional fully managed display mode, provisioned explicitly by school IT. */
public final class KioskAdmin extends DeviceAdminReceiver {
    static boolean isOwner(Context context) {
        DevicePolicyManager policy = context.getSystemService(DevicePolicyManager.class);
        return policy != null && policy.isDeviceOwnerApp(context.getPackageName());
    }

    static boolean configure(Context context) {
        if (!isOwner(context)) return false;
        DevicePolicyManager policy = context.getSystemService(DevicePolicyManager.class);
        ComponentName admin = new ComponentName(context, KioskAdmin.class);
        IntentFilter home = new IntentFilter(Intent.ACTION_MAIN);
        home.addCategory(Intent.CATEGORY_HOME);
        home.addCategory(Intent.CATEGORY_DEFAULT);
        policy.addPersistentPreferredActivity(admin, home, new ComponentName(context, MainActivity.class));
        policy.setLockTaskPackages(admin, new String[]{context.getPackageName()});
        return true;
    }

    @Override public void onEnabled(Context context, Intent intent) {
        configure(context);
    }
}
