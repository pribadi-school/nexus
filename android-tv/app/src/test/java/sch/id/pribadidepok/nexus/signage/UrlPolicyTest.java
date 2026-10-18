package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import static org.junit.Assert.*;

public class UrlPolicyTest {
    @Test public void normalizesHttpsServer() { assertEquals("https://nexus.example", UrlPolicy.server(" https://nexus.example/ ")); }
    @Test public void localServerIsAllowed() { assertEquals("http://192.168.1.20:8000", UrlPolicy.server("http://192.168.1.20:8000/")); }
    @Test public void privateAddressesOnly() {
        assertTrue(UrlPolicy.privateHost("10.0.2.2")); assertTrue(UrlPolicy.privateHost("172.31.8.2"));
        assertTrue(UrlPolicy.privateHost("127.0.0.1")); assertTrue(UrlPolicy.privateHost("[::1]"));
        assertFalse(UrlPolicy.privateHost("172.32.0.1")); assertFalse(UrlPolicy.privateHost("192.168.1.999")); assertFalse(UrlPolicy.privateHost("10.0.0.999"));
    }
    @Test(expected=IllegalArgumentException.class) public void rejectsPublicHttp() { UrlPolicy.server("http://example.com"); }
    @Test(expected=IllegalArgumentException.class) public void rejectsCredentials() { UrlPolicy.server("https://admin:secret@example.com"); }
    @Test(expected=IllegalArgumentException.class) public void rejectsJavascript() { UrlPolicy.server("javascript:alert(1)"); }
    @Test(expected=IllegalArgumentException.class) public void rejectsServerQuery() { UrlPolicy.server("https://example.com/?token=secret"); }
    @Test public void resolvesSameOriginMedia() { assertEquals("https://nexus.example/signage/media/1?v=a", UrlPolicy.nexus("https://nexus.example", "/signage/media/1?v=a")); }
    @Test(expected=IllegalArgumentException.class) public void rejectsCrossOriginMedia() { UrlPolicy.nexus("https://nexus.example", "https://evil.example/video.mp4"); }
    @Test(expected=IllegalArgumentException.class) public void rejectsSchemeRelativeMedia() { UrlPolicy.nexus("https://nexus.example", "//evil.example/video.mp4"); }
    @Test(expected=IllegalArgumentException.class) public void rejectsDowngrade() { UrlPolicy.nexus("https://nexus.example", "http://nexus.example/video.mp4"); }
    @Test(expected=IllegalArgumentException.class) public void rejectsOtherPort() { UrlPolicy.nexus("https://nexus.example", "https://nexus.example:8443/video.mp4"); }
    @Test public void versionChangesCacheIdentity() { assertNotEquals(UrlPolicy.cacheKey("https://nexus.example/1?v=a"), UrlPolicy.cacheKey("https://nexus.example/1?v=b")); }
    @Test public void cacheNamesAreSafe() { assertTrue(UrlPolicy.cacheKey("../../something.mp4").matches("[a-f0-9]{64}")); }
    @Test public void websiteSchemesAreLimited() { assertTrue(UrlPolicy.website("https://example.com/")); assertTrue(UrlPolicy.website("http://192.168.1.20/")); assertFalse(UrlPolicy.website("file:///sdcard/a")); assertFalse(UrlPolicy.website("intent:abc")); assertFalse(UrlPolicy.website("data:text/html,abc")); }
}
