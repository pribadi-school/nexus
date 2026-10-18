package sch.id.pribadidepok.nexus.signage;

import org.json.JSONObject;
import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.Map;

final class NexusApi {
    static final class Failure extends IOException {
        final int status;
        Failure(int status, String message) { super(message); this.status = status; }
    }
    final String base;
    NexusApi(String base) { this.base = UrlPolicy.server(base); }

    HttpURLConnection open(String address) throws IOException {
        HttpURLConnection connection = (HttpURLConnection) new URL(UrlPolicy.nexus(base, address)).openConnection();
        connection.setConnectTimeout(10000);
        connection.setReadTimeout(20000);
        connection.setInstanceFollowRedirects(false);
        connection.setRequestProperty("User-Agent", "NexusSignageTV/1.0");
        connection.setRequestProperty("Accept-Encoding", "identity");
        return connection;
    }

    JSONObject get(String path) throws Exception { return json(path, null); }
    JSONObject post(String path, Map<String, String> fields) throws Exception { return json(path, fields); }
    private JSONObject json(String path, Map<String, String> fields) throws Exception {
        HttpURLConnection connection = open(path);
        try {
            connection.setRequestProperty("Accept", "application/json");
            connection.setRequestProperty("X-Requested-With", "fetch");
            if (fields != null) {
                StringBuilder body = new StringBuilder();
                for (Map.Entry<String, String> field : fields.entrySet()) {
                    if (body.length() > 0) body.append('&');
                    body.append(encode(field.getKey())).append('=').append(encode(field.getValue()));
                }
                byte[] bytes = body.toString().getBytes(StandardCharsets.UTF_8);
                connection.setRequestMethod("POST");
                connection.setDoOutput(true);
                connection.setFixedLengthStreamingMode(bytes.length);
                connection.setRequestProperty("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
                try (java.io.OutputStream stream = connection.getOutputStream()) { stream.write(bytes); }
            }
            int status = connection.getResponseCode();
            if (status < 200 || status > 299) {
                throw new Failure(status, status == 429 ? "Nexus is busy. Wait before trying again."
                        : "Nexus request failed (" + status + ").");
            }
            try (InputStream input = connection.getInputStream(); ByteArrayOutputStream output = new ByteArrayOutputStream()) {
                byte[] bytes = new byte[8192]; int count;
                while ((count = input.read(bytes)) != -1) {
                    if (output.size() + count > 2 * 1024 * 1024) throw new IOException("The playlist response is too large.");
                    output.write(bytes, 0, count);
                }
                return new JSONObject(output.toString(StandardCharsets.UTF_8.name()));
            }
        } finally { connection.disconnect(); }
    }
    static String encode(String value) {
        try { return URLEncoder.encode(value, "UTF-8"); }
        catch (java.io.UnsupportedEncodingException impossible) { throw new IllegalStateException(impossible); }
    }
}
