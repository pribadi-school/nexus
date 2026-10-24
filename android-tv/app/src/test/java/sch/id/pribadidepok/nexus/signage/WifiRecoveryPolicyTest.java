package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import static org.junit.Assert.*;

public class WifiRecoveryPolicyTest {
    @Test public void portraitAndLegacyAndroidRetainAutomaticRecovery() {
        for (int api = 25; api <= 28; api++) {
            assertTrue(WifiRecoveryPolicy.supported(api, 36));
            assertTrue(WifiRecoveryPolicy.supported(api, 28));
        }
    }

    @Test public void android10LandscapeBuildCanUseTheCompatibilityPermission() {
        assertTrue(WifiRecoveryPolicy.supported(29, 28));
    }

    @Test public void modernTargetCannotToggleWifiOnAndroid10() {
        assertFalse(WifiRecoveryPolicy.supported(29, 29));
        assertFalse(WifiRecoveryPolicy.supported(29, 36));
    }

    @Test public void newAndroidCannotEnterALegacyForegroundServicePath() {
        for (int api = 30; api <= 37; api++) {
            assertFalse(WifiRecoveryPolicy.supported(api, 28));
            assertFalse(WifiRecoveryPolicy.supported(api, 36));
        }
    }

    @Test public void olderThanMinimumSdkIsNotAdvertisedAsSupported() {
        assertFalse(WifiRecoveryPolicy.supported(24, 28));
    }
}
