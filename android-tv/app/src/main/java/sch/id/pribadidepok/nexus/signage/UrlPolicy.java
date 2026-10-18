package sch.id.pribadidepok.nexus.signage;

import java.net.URI;
import java.util.Locale;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

/** Network rules shared by pairing, manifests and private media downloads. */
public final class UrlPolicy {
    private UrlPolicy() {}

    public static String server(String input) {
        try {
            URI uri = new URI(input.trim());
            String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase(Locale.ROOT);
            String host = uri.getHost();
            if (host == null || uri.getRawUserInfo() != null || uri.getRawQuery() != null || uri.getRawFragment() != null
                    || (!scheme.equals("https") && !scheme.equals("http")) || uri.getPort() > 65535 || uri.getPort() == 0) {
                throw new IllegalArgumentException("Enter a full Nexus HTTPS address, without a query or sign-in details.");
            }
            if (scheme.equals("http") && !privateHost(host)) {
                throw new IllegalArgumentException("Use HTTPS for an internet server. HTTP is allowed only on a private local network.");
            }
            String result = uri.toASCIIString();
            while (result.endsWith("/")) result = result.substring(0, result.length() - 1);
            return result;
        } catch (java.net.URISyntaxException exception) {
            throw new IllegalArgumentException("Enter the full Nexus server address, for example https://nexus.pribadidepok.sch.id.");
        }
    }

    public static boolean privateHost(String input) {
        String host = input.toLowerCase(Locale.ROOT).replace("[", "").replace("]", "");
        if (host.equals("localhost") || host.equals("::1") || host.endsWith(".local")) return true;
        String[] parts = host.split("\\.");
        if (parts.length != 4) return false;
        int[] octets = new int[4];
        try {
            for (int index = 0; index < 4; index++) {
                if (!parts[index].matches("[0-9]{1,3}")) return false;
                octets[index] = Integer.parseInt(parts[index]);
                if (octets[index] > 255) return false;
            }
        } catch (NumberFormatException exception) { return false; }
        return octets[0] == 10 || octets[0] == 127
                || (octets[0] == 192 && octets[1] == 168)
                || (octets[0] == 172 && octets[1] >= 16 && octets[1] <= 31)
                || (octets[0] == 169 && octets[1] == 254);
    }

    public static String nexus(String base, String address) {
        try {
            URI origin = URI.create(server(base));
            URI resolved = origin.resolve(address);
            if (resolved.getRawUserInfo() != null || resolved.getHost() == null
                    || !origin.getScheme().equalsIgnoreCase(resolved.getScheme())
                    || !origin.getHost().equalsIgnoreCase(resolved.getHost()) || port(origin) != port(resolved)) {
                throw new IllegalArgumentException("Nexus returned a file address on a different server.");
            }
            return resolved.toASCIIString();
        } catch (IllegalArgumentException exception) { throw new IllegalArgumentException("Nexus returned an invalid server address."); }
    }

    public static boolean website(String address) {
        try {
            URI uri = URI.create(address);
            return uri.getHost() != null && uri.getRawUserInfo() == null
                    && ("https".equalsIgnoreCase(uri.getScheme()) || "http".equalsIgnoreCase(uri.getScheme()));
        } catch (IllegalArgumentException exception) { return false; }
    }

    private static int port(URI uri) { return uri.getPort() >= 0 ? uri.getPort() : ("https".equalsIgnoreCase(uri.getScheme()) ? 443 : 80); }

    public static String cacheKey(String url) {
        try {
            byte[] digest = MessageDigest.getInstance("SHA-256").digest(url.getBytes(StandardCharsets.UTF_8));
            StringBuilder result = new StringBuilder();
            for (byte value : digest) result.append(String.format(Locale.ROOT, "%02x", value & 255));
            return result.toString();
        } catch (java.security.NoSuchAlgorithmException exception) { throw new IllegalStateException(exception); }
    }
}
