package sch.id.pribadidepok.nexus.signage;

/** Website layout uses CSS pixels, independently of the TV's Android density. */
final class WebsiteLayout {
    static final int MIN_DESKTOP_WIDTH = 1280;

    static final class Size {
        final int width, height;
        final float scale;
        Size(int width, int height, float scale) { this.width = width; this.height = height; this.scale = scale; }
    }

    static Size fit(int availableWidth, int availableHeight, float density) {
        int width = Math.max(1, availableWidth), height = Math.max(1, availableHeight);
        float pixelsPerCssPixel = Float.isFinite(density) && density > 0 ? density : 1;
        float scale = Math.min(1f, width / (MIN_DESKTOP_WIDTH * pixelsPerCssPixel));
        return new Size(Math.round(width / scale), Math.round(height / scale), scale);
    }

    static String desktopUserAgent(String original) {
        // Keep the installed Chromium version; change only the mobile platform hints.
        return original.replaceFirst("\\([^)]*\\)", "(X11; Linux x86_64)")
            .replace(" Version/4.0", "").replace(" Mobile", "");
    }
}
