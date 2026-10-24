package sch.id.pribadidepok.nexus.signage;

/** Bound unattended radio control to the Android versions this keeper supports. */
final class WifiRecoveryPolicy {
    private WifiRecoveryPolicy() {}

    static boolean supported(int androidApi, int targetApi) {
        // API 29 honours CHANGE_WIFI_STATE only for targets <= 28. Never run
        // this legacy foreground service on newer, unvalidated OS versions.
        return androidApi >= 25 && (androidApi < 29 || androidApi == 29 && targetApi <= 28);
    }
}
