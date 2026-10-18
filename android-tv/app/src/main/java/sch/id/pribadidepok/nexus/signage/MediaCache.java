package sch.id.pribadidepok.nexus.signage;

import android.content.Context;
import android.util.AtomicFile;
import org.json.JSONArray;
import org.json.JSONObject;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.nio.charset.StandardCharsets;
import java.util.HashSet;
import java.util.Set;
import java.util.function.BooleanSupplier;

/** Complete files and an atomic manifest survive restarts without OS cache eviction. */
final class MediaCache {
    static final long MAX_BYTES = 2L * 1024 * 1024 * 1024;
    private static final long RESERVE_BYTES = 100L * 1024 * 1024;
    private final File directory;
    private final AtomicFile manifestFile;
    MediaCache(Context context) {
        directory = new File(context.getFilesDir(), "signage");
        if (!directory.isDirectory() && !directory.mkdirs()) throw new IllegalStateException("Cannot create the video storage folder.");
        manifestFile = new AtomicFile(new File(directory, "playlist.json"));
    }

    File video(String base, JSONObject item) { return new File(directory, UrlPolicy.cacheKey(UrlPolicy.nexus(base, item.optString("url"))) + ".mp4"); }

    JSONObject read(String base) {
        try {
            JSONObject data = new JSONObject(new String(manifestFile.readFully(), StandardCharsets.UTF_8));
            JSONArray items = data.getJSONArray("items");
            for (int index = 0; index < items.length(); index++) {
                JSONObject item = items.getJSONObject(index);
                if ("video".equals(item.optString("type")) && !valid(video(base, item), item.optLong("size_bytes", 0))) return null;
            }
            return data;
        } catch (Exception exception) { return null; }
    }

    JSONObject prepare(NexusApi api, JSONObject data, BooleanSupplier cancelled, java.util.function.Consumer<String> progress) throws Exception {
        // A process killed during download leaves an incomplete file. It cannot
        // be played or resumed, and must not block its own retry's space check.
        File[] interrupted = directory.listFiles();
        if (interrupted != null) for (File partial : interrupted) {
            if (partial.isFile() && partial.getName().matches("[a-f0-9]{64}\\.mp4\\.part") && !partial.delete()) {
                throw new IOException("Cannot remove an interrupted download. Check the TV's storage.");
            }
        }
        JSONArray items = data.getJSONArray("items");
        long total = 0;
        long required = 0;
        Set<String> seen = new HashSet<>();
        for (int index = 0; index < items.length(); index++) {
            JSONObject item = items.getJSONObject(index);
            if (!"video".equals(item.optString("type"))) continue;
            File target = video(api.base, item);
            long size = item.optLong("size_bytes", 0);
            if (size < 1 || size > MAX_BYTES) throw new IOException("A video has an invalid size. Update the Nexus server and check the uploaded file.");
            if (seen.add(target.getName())) {
                total += size;
                if (total > MAX_BYTES) throw new IOException("This playlist exceeds the TV app's 2 GB video limit. Use a smaller playlist.");
                if (!valid(target, size)) required += size;
            }
        }
        if (required + RESERVE_BYTES > directory.getUsableSpace()) throw new IOException("Not enough space to update safely. The downloaded playlist will keep playing.");
        for (int index = 0; index < items.length(); index++) {
            if (cancelled.getAsBoolean()) throw new IOException("Download cancelled.");
            JSONObject item = items.getJSONObject(index);
            if (!"video".equals(item.optString("type"))) continue;
            File target = video(api.base, item);
            long expected = item.optLong("size_bytes", 0);
            if (valid(target, expected)) continue;
            progress.accept("Downloading " + item.optString("name", "video") + "…");
            File temporary = new File(directory, target.getName() + ".part");
            HttpURLConnection connection = api.open(item.getString("url"));
            try {
                int status = connection.getResponseCode();
                if (status != 200) throw new NexusApi.Failure(status, "A video download failed (" + status + ").");
                long announced = connection.getContentLengthLong();
                if (announced > 0 && announced != expected) throw new IOException("A video changed during download. Retrying the latest playlist.");
                long written = 0;
                try (InputStream input = connection.getInputStream(); FileOutputStream output = new FileOutputStream(temporary)) {
                    byte[] buffer = new byte[65536]; int count;
                    while ((count = input.read(buffer)) != -1) {
                        if (cancelled.getAsBoolean()) throw new IOException("Download cancelled.");
                        written += count;
                        if (written > expected || written > MAX_BYTES) throw new IOException("The video download is larger than expected.");
                        output.write(buffer, 0, count);
                    }
                    output.getFD().sync();
                }
                if (written != expected) throw new IOException("The video download was interrupted. Keeping the previous playlist.");
                if (!temporary.renameTo(target)) throw new IOException("Cannot save a downloaded video.");
            } finally {
                connection.disconnect();
                if (temporary.exists() && !temporary.delete()) temporary.deleteOnExit();
            }
        }
        return data;
    }

    void save(JSONObject data) throws IOException {
        FileOutputStream output = manifestFile.startWrite();
        try {
            output.write(data.toString().getBytes(StandardCharsets.UTF_8));
            manifestFile.finishWrite(output);
        } catch (IOException exception) { manifestFile.failWrite(output); throw exception; }
    }

    void prune(String base, JSONObject data) {
        Set<String> keep = new HashSet<>();
        JSONArray items = data.optJSONArray("items");
        if (items == null) return;
        for (int index = 0; index < items.length(); index++) {
            JSONObject item = items.optJSONObject(index);
            if (item != null && "video".equals(item.optString("type"))) keep.add(video(base, item).getName());
        }
        File[] files = directory.listFiles();
        if (files != null) for (File file : files) {
            if (file.getName().matches("[a-f0-9]{64}\\.mp4(\\.part)?") && !keep.contains(file.getName())) file.delete();
        }
    }

    void clear() {
        manifestFile.delete();
        File[] files = directory.listFiles();
        if (files != null) for (File file : files) if (file.isFile() && file.getName().matches("[a-f0-9]{64}\\.mp4(\\.part)?")) file.delete();
    }
    private boolean valid(File file, long expected) { return expected > 0 && file.isFile() && file.length() == expected; }
}
