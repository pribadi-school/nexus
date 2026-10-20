package sch.id.pribadidepok.nexus.signage;

import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Matrix;
import androidx.exifinterface.media.ExifInterface;
import java.io.File;
import java.io.IOException;

/** Bounded image decoding with upright JPEG photos on Android 7.1 and later. */
final class ImageLoader {
    private ImageLoader() {}

    static Bitmap load(File file, int width, int height, long maxBytes) {
        Bitmap decoded = null;
        Bitmap transformed = null;
        try {
            if (file == null || !file.isFile() || maxBytes < 4) return null;
            BitmapFactory.Options options = new BitmapFactory.Options();
            options.inJustDecodeBounds = true;
            BitmapFactory.decodeFile(file.getPath(), options);
            if (options.outWidth < 1 || options.outHeight < 1) return null;

            int orientation = ExifInterface.ORIENTATION_NORMAL;
            // Parse camera orientation with the maintained AndroidX reader,
            // independently of this old panel's framework implementation.
            if ("image/jpeg".equals(options.outMimeType)) {
                try {
                    orientation = new ExifInterface(file.getPath()).getAttributeInt(
                        ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL);
                } catch (IOException unreadableMetadata) { /* A decodable JPEG can have broken EXIF. */ }
            }
            boolean transform = orientation >= ExifInterface.ORIENTATION_FLIP_HORIZONTAL
                && orientation <= ExifInterface.ORIENTATION_ROTATE_270;
            // A transform briefly retains both source and destination bitmaps.
            if (transform && maxBytes < 8) return null;
            long decodeBudget = transform ? maxBytes / 2 : maxBytes;
            options.inSampleSize = DisplayLayout.imageSampleSize(
                options.outWidth, options.outHeight, width, height, decodeBudget);
            options.inJustDecodeBounds = false;
            options.inPreferredConfig = Bitmap.Config.ARGB_8888;
            options.inScaled = false;
            decoded = BitmapFactory.decodeFile(file.getPath(), options);
            if (decoded == null || !transform) return decoded;

            Matrix matrix = new Matrix();
            switch (orientation) {
                case ExifInterface.ORIENTATION_FLIP_HORIZONTAL:
                    matrix.setScale(-1, 1);
                    break;
                case ExifInterface.ORIENTATION_ROTATE_180:
                    matrix.setRotate(180);
                    break;
                case ExifInterface.ORIENTATION_FLIP_VERTICAL:
                    matrix.setScale(1, -1);
                    break;
                case ExifInterface.ORIENTATION_TRANSPOSE:
                    matrix.setRotate(90);
                    matrix.postScale(-1, 1);
                    break;
                case ExifInterface.ORIENTATION_ROTATE_90:
                    matrix.setRotate(90);
                    break;
                case ExifInterface.ORIENTATION_TRANSVERSE:
                    matrix.setRotate(-90);
                    matrix.postScale(-1, 1);
                    break;
                case ExifInterface.ORIENTATION_ROTATE_270:
                    matrix.setRotate(-90);
                    break;
                default:
                    return decoded;
            }
            transformed = Bitmap.createBitmap(decoded, 0, 0,
                decoded.getWidth(), decoded.getHeight(), matrix, true);
            if (transformed != decoded) decoded.recycle();
            return transformed;
        } catch (RuntimeException | OutOfMemoryError failure) {
            if (transformed != null && transformed != decoded && !transformed.isRecycled()) transformed.recycle();
            if (decoded != null && !decoded.isRecycled()) decoded.recycle();
            return null;
        }
    }
}
