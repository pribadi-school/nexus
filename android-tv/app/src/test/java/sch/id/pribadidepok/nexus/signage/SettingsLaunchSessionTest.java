package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import java.util.ArrayList;
import java.util.List;
import static org.junit.Assert.*;

public class SettingsLaunchSessionTest {
    static final class Fixture implements SettingsLaunchSession.Timer {
        final List<Integer> launches = new ArrayList<>();
        final List<String> messages = new ArrayList<>();
        Runnable pending; long delay; boolean throwLaunch;
        final SettingsLaunchSession session = new SettingsLaunchSession(index -> {
            launches.add(index); if (throwLaunch) throw new SecurityException("blocked");
        }, this, messages::add);
        @Override public void post(Runnable callback, long delay) { assertNull(pending); pending = callback; this.delay = delay; }
        @Override public void remove(Runnable callback) { if (pending == callback) pending = null; }
        void fire() { Runnable callback = pending; pending = null; assertNotNull(callback); callback.run(); }
    }

    @Test public void feedbackDrawsBeforeAnyAndroidLaunch() {
        Fixture f = new Fixture(); f.session.start("Wi-Fi", 2);
        assertTrue(f.launches.isEmpty()); assertEquals(250, f.delay);
        assertTrue(f.messages.get(0).contains("Opening Wi-Fi"));
        f.fire(); assertEquals(List.of(0), f.launches); assertEquals(2000, f.delay);
    }
    @Test public void silentlyBlockedLaunchTriesNextTargetThenReportsFailure() {
        Fixture f = new Fixture(); f.session.start("Wi-Fi", 2);
        f.fire(); f.fire(); assertEquals(List.of(0, 1), f.launches); f.fire();
        assertFalse(f.session.running()); assertNull(f.pending);
        assertTrue(f.messages.get(f.messages.size() - 1).contains("could not open"));
    }
    @Test public void throwingLaunchesAdvanceThroughFallbacksAndRemainRetryable() {
        Fixture f = new Fixture(); f.throwLaunch = true; f.session.start("Settings", 3); f.fire();
        assertEquals(List.of(0, 1, 2), f.launches); assertFalse(f.session.running());
        f.throwLaunch = false; f.session.start("Apps", 1); f.fire(); assertTrue(f.session.running());
    }
    @Test public void leavingRecoveryCancelsFallbacksWithoutClaimingSettingsWorked() {
        Fixture f = new Fixture(); f.session.start("Wi-Fi", 3); f.fire();
        f.session.paused(); assertNull(f.pending); assertEquals(1, f.messages.size());
        f.session.resumed(); assertFalse(f.session.running()); assertEquals(List.of(0), f.launches);
        assertTrue(f.messages.get(1).contains("If Settings froze"));
    }
    @Test public void initialResumeDoesNotCancelANotYetLaunchedAttempt() {
        Fixture f = new Fixture(); f.session.resumed(); f.session.start("Apps", 1);
        f.session.resumed(); f.fire(); assertEquals(List.of(0), f.launches);
    }
    @Test public void destroyInvalidatesEvenAnAlreadyDeliveredFallback() {
        Fixture f = new Fixture(); f.session.start("Settings", 2); f.fire();
        Runnable stale = f.pending; f.session.cancel(); stale.run();
        assertEquals(List.of(0), f.launches); assertNull(f.pending);
    }
    @Test public void oldCallbacksCannotLaunchAfterANewAttemptStarts() {
        Fixture f = new Fixture(); f.session.start("Wi-Fi", 3); f.fire();
        Runnable stale = f.pending; f.session.cancel(); f.session.start("Apps", 1);
        stale.run(); assertEquals(List.of(0), f.launches); f.fire(); assertEquals(List.of(0, 0), f.launches);
    }
    @Test public void repeatedClicksDoNotReplaceAnInFlightLaunch() {
        Fixture f = new Fixture(); f.session.start("Wi-Fi", 2); f.session.start("Apps", 1);
        f.fire(); f.fire(); assertEquals(List.of(0, 1), f.launches);
        assertTrue(f.messages.get(0).contains("Wi-Fi"));
    }
}
