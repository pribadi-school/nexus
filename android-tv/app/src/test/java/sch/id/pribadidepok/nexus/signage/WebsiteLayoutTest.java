package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import static org.junit.Assert.*;

public class WebsiteLayoutTest {
    private void assertFits(int width, int height, float density) {
        WebsiteLayout.Size size = WebsiteLayout.fit(width, height, density);
        assertTrue("desktop CSS width", size.width / density >= 1279.9f);
        assertTrue("never enlarge a page", size.scale <= 1);
        assertEquals("no horizontal crop", width, size.width * size.scale, 1f);
        assertEquals("no vertical crop", height, size.height * size.scale, 1f);
        assertEquals("preserve aspect ratio", (float) width / height, (float) size.width / size.height, .002f);
    }
    @Test public void fullHdAtTvDensity() { assertFits(1920, 1080, 2); }
    @Test public void hdReadyAtTvDensity() { assertFits(1280, 720, 1.5f); }
    @Test public void fourK() { assertFits(3840, 2160, 2); }
    @Test public void portraitMediaArea() { assertFits(1080, 1920, 2); }
    @Test public void resizingMaintainsDesktopWidth() { assertFits(960, 540, 1); assertFits(1600, 900, 1); }
    @Test public void largeDesktopIsNotUpscaled() { assertEquals(1, WebsiteLayout.fit(1920, 1080, 1).scale, 0); }
    @Test public void badDimensionsAreSafe() {
        WebsiteLayout.Size size = WebsiteLayout.fit(0, 0, Float.NaN);
        assertTrue(size.width > 0 && size.height > 0 && Float.isFinite(size.scale) && size.scale > 0);
    }
    @Test public void userAgentKeepsTheRealBrowserVersion() {
        String mobile = "Mozilla/5.0 (Linux; Android 11; TV Build/X; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/131.0.6778.200 Mobile Safari/537.36";
        String desktop = WebsiteLayout.desktopUserAgent(mobile);
        assertTrue(desktop.contains("(X11; Linux x86_64)"));
        assertTrue(desktop.contains("Chrome/131.0.6778.200"));
        assertFalse(desktop.contains("Android")); assertFalse(desktop.contains("Mobile")); assertFalse(desktop.contains("wv"));
    }
    @Test public void desktopUserAgentIsUnchanged() {
        String desktop = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.200 Safari/537.36";
        assertEquals(desktop, WebsiteLayout.desktopUserAgent(desktop));
    }
}
