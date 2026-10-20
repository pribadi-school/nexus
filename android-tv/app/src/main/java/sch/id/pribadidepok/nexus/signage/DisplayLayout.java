package sch.id.pribadidepok.nexus.signage;

/** Pixel geometry shared by native portrait panels and rotated landscape TVs. */
final class DisplayLayout {
    static final class Size {
        final int width, height, rotationDegrees;

        Size(int width, int height, int rotationDegrees) {
            this.width = width;
            this.height = height;
            this.rotationDegrees = rotationDegrees;
        }
    }

    static Size fit(int availableWidth, int availableHeight, String orientation) {
        int width = Math.max(1, availableWidth);
        int height = Math.max(1, availableHeight);
        // A native portrait display already has the correct coordinate space.
        // Keep the server's default landscape value from turning it sideways.
        boolean rotate = "portrait".equals(orientation) && width > height;
        return new Size(rotate ? height : width, rotate ? width : height, rotate ? 90 : 0);
    }

    /** Choose Android's power-of-two decoder sampling before allocating pixels. */
    static int imageSampleSize(int sourceWidth, int sourceHeight,
            int targetWidth, int targetHeight, long maxBytes) {
        if (sourceWidth < 1 || sourceHeight < 1) return 1;
        long targetPixels = (long) Math.max(1, targetWidth) * Math.max(1, targetHeight);
        // ARGB_8888 uses four bytes per pixel. One decoded pixel is the minimum.
        long pixelBudget = Math.max(1L, Math.min(targetPixels, Math.max(0L, maxBytes) / 4L));
        int sample = 1;
        while (decodedPixels(sourceWidth, sourceHeight, sample) > pixelBudget && sample < (1 << 30)) {
            sample *= 2;
        }
        return sample;
    }

    private static long decodedPixels(int width, int height, int sample) {
        // Round up so odd-sized images cannot exceed the requested budget.
        long decodedWidth = ((long) width + sample - 1) / sample;
        long decodedHeight = ((long) height + sample - 1) / sample;
        return decodedWidth * decodedHeight;
    }
}
