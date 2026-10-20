package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import static org.junit.Assert.*;

public class DisplayLayoutTest {
    @Test public void nativeFourKPortraitIsNotRotatedTwice() {
        DisplayLayout.Size size = DisplayLayout.fit(2160, 3840, "portrait");
        assertEquals(0, size.rotationDegrees);
        assertEquals(2160, size.width);
        assertEquals(3840, size.height);
    }

    @Test public void defaultServerOrientationKeepsNativePortraitUpright() {
        DisplayLayout.Size size = DisplayLayout.fit(2160, 3840, "landscape");
        assertEquals(0, size.rotationDegrees);
        assertEquals(2160, size.width);
        assertEquals(3840, size.height);
    }

    @Test public void landscapeTvRetainsExistingPortraitRotation() {
        DisplayLayout.Size size = DisplayLayout.fit(3840, 2160, "portrait");
        assertEquals(90, size.rotationDegrees);
        assertEquals(2160, size.width);
        assertEquals(3840, size.height);
        // Rotating those dimensions occupies the whole original viewport.
        assertEquals(3840, size.height);
        assertEquals(2160, size.width);
    }

    @Test public void ordinaryLandscapeRemainsUnchanged() {
        DisplayLayout.Size size = DisplayLayout.fit(3840, 2160, "landscape");
        assertEquals(0, size.rotationDegrees);
        assertEquals(3840, size.width);
        assertEquals(2160, size.height);
    }

    @Test public void geometryUsesPhysicalPixelsAtEveryDensity() {
        for (int[] viewport : new int[][]{{720,1280},{1080,1920},{2160,3840}}) {
            DisplayLayout.Size size = DisplayLayout.fit(viewport[0], viewport[1], "portrait");
            assertEquals(viewport[0], size.width);
            assertEquals(viewport[1], size.height);
            assertEquals(0, size.rotationDegrees);
        }
    }

    @Test public void missingOrientationAndEarlyMeasurementAreSafe() {
        DisplayLayout.Size size = DisplayLayout.fit(0, -1, null);
        assertEquals(1, size.width);
        assertEquals(1, size.height);
        assertEquals(0, size.rotationDegrees);
        assertEquals(0, DisplayLayout.fit(100, 100, "portrait").rotationDegrees);
    }

    @Test public void eightKPortraitImageFitsFourKAndThirtyTwoMegabytes() {
        int sample = DisplayLayout.imageSampleSize(4320, 7680, 2160, 3840, 32L * 1024 * 1024);
        assertEquals(2, sample);
        assertBudget(4320, 7680, sample, 2160L * 3840, 32L * 1024 * 1024);
    }

    @Test public void smallHeapBudgetWinsOverPanelResolution() {
        int sample = DisplayLayout.imageSampleSize(4320, 7680, 2160, 3840, 8L * 1024 * 1024);
        assertEquals(4, sample);
        assertBudget(4320, 7680, sample, 2160L * 3840, 8L * 1024 * 1024);
    }

    @Test public void oddDimensionsDoNotRoundBelowThePixelBudget() {
        int sample = DisplayLayout.imageSampleSize(8193, 8193, 4096, 4096, Long.MAX_VALUE);
        assertEquals(4, sample);
        assertBudget(8193, 8193, sample, 4096L * 4096, Long.MAX_VALUE);
    }

    @Test public void thumbnailIsNeverUpsampled() {
        assertEquals(1, DisplayLayout.imageSampleSize(100, 200, 2160, 3840, 32L * 1024 * 1024));
        assertEquals(1, DisplayLayout.imageSampleSize(-1, -1, 2160, 3840, 32L * 1024 * 1024));
    }

    @Test public void hugeMetadataCannotOverflowPixelArithmetic() {
        int sample = DisplayLayout.imageSampleSize(Integer.MAX_VALUE, Integer.MAX_VALUE, 2160, 3840, 32L * 1024 * 1024);
        assertTrue(sample > 1);
        assertEquals(0, sample & (sample - 1));
        assertBudget(Integer.MAX_VALUE, Integer.MAX_VALUE, sample, 2160L * 3840, 32L * 1024 * 1024);
    }

    private void assertBudget(int width, int height, int sample, long targetPixels, long maxBytes) {
        long decodedPixels = (((long) width + sample - 1) / sample) * (((long) height + sample - 1) / sample);
        assertTrue("decoded pixels fit viewport", decodedPixels <= targetPixels);
        assertTrue("decoded allocation fits memory budget", decodedPixels <= maxBytes / 4);
    }
}
