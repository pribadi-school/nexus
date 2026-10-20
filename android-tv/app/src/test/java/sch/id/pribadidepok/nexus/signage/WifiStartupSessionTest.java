package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import java.util.ArrayList;
import java.util.List;
import static org.junit.Assert.*;

/** The radio can become available well after the launcher starts on the T960X. */
public class WifiStartupSessionTest {
    private static final long SECOND = 1000;
    private static final long STEP = 5 * SECOND;
    private static final long MINUTE = 60 * SECOND;

    @Test public void disabledPreferenceDoesNotReadOrChangeTheRadio() {
        Fixture f = new Fixture();
        f.session.start(false, true);
        f.session.wake();
        f.timer.advance(2 * MINUTE);
        f.assertUntouched();
        f.assertStopped();
    }

    @Test public void unsupportedAndroidDoesNotReadOrChangeTheRadio() {
        Fixture f = new Fixture();
        f.session.start(true, false);
        f.session.wake();
        f.timer.advance(2 * MINUTE);
        f.assertUntouched();
        f.assertStopped();
    }

    @Test public void alreadyEnabledRadioIsMonitoredWithoutEnableRequests() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.start();
        assertEquals(1, f.radio.stateReads);
        f.timer.advance(15 * SECOND - 1);
        assertEquals(1, f.radio.stateReads);
        f.timer.advance(1);
        assertEquals(2, f.radio.stateReads);
        assertEquals(0, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void disabledRadioIsRequestedImmediatelyAndObservedAsynchronously() {
        Fixture f = new Fixture();
        f.start();
        assertEquals(1, f.radio.enableCalls);
        f.radio.state = WifiStartupSession.State.ENABLING;
        f.timer.advance(STEP);
        assertEquals(1, f.radio.enableCalls);
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.timer.advance(STEP);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void acceptedButIneffectiveRequestsRetryWithCappedBackoff() {
        Fixture f = new Fixture();
        f.start();
        assertRequestTimes(f, 0);
        f.timer.advance(15 * SECOND - 1);
        assertEquals(1, f.radio.enableCalls);
        f.timer.advance(1);
        assertRequestTimes(f, 0, 15000);
        f.timer.advance(30 * SECOND);
        assertRequestTimes(f, 0, 15000, 45000);
        f.timer.advance(MINUTE);
        assertRequestTimes(f, 0, 15000, 45000, 105000);
        f.timer.advance(MINUTE);
        assertRequestTimes(f, 0, 15000, 45000, 105000, 165000);
        f.assertMonitoring();
    }

    @Test public void rejectedRequestsAlsoKeepRetryingWithoutAThreeRequestLimit() {
        Fixture f = new Fixture();
        f.radio.accept = false;
        f.start();
        f.timer.advance(165 * SECOND);
        assertRequestTimes(f, 0, 15000, 45000, 105000, 165000);
        f.assertMonitoring();
    }

    @Test public void startupCanSucceedAfterTheOldOneMinuteWindow() {
        Fixture f = new Fixture();
        f.start();
        f.timer.advance(2 * MINUTE);
        assertTrue(f.radio.enableCalls > 1);
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.timer.advance(STEP);
        int calls = f.radio.enableCalls;
        f.timer.advance(2 * MINUTE);
        assertEquals(calls, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void initialEnablingStateWaitsWithoutRepeatedEnableRequests() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLING;
        f.start();
        f.timer.advance(2 * MINUTE);
        assertEquals(0, f.radio.enableCalls);
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.timer.advance(STEP);
        f.assertMonitoring();
    }

    @Test public void initialEnablingThenOffAutomaticallyTriesAgain() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLING;
        f.start();
        f.timer.advance(STEP);
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.timer.advance(STEP);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void acceptedThenEnablingThenOffDoesNotRequireTheRetryButton() {
        Fixture f = new Fixture();
        f.start();
        f.radio.state = WifiStartupSession.State.ENABLING;
        f.timer.advance(STEP);
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.timer.advance(STEP);
        assertEquals(1, f.radio.enableCalls);
        f.timer.advance(STEP);
        assertRequestTimes(f, 0, 15000);
        f.assertMonitoring();
    }

    @Test public void radioTurningOffAfterSuccessIsEnabledAgainAutomatically() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.start();
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.timer.advance(15 * SECOND);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void briefSuccessResetsBackoffButKeepsTheExistingEnableCooldown() {
        Fixture f = new Fixture();
        f.start();
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.timer.advance(STEP);
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.session.wake();
        assertEquals(1, f.radio.enableCalls);
        f.timer.advance(STEP);
        f.session.wake();
        assertEquals(1, f.radio.enableCalls);
        f.timer.advance(STEP);
        assertRequestTimes(f, 0, 15000);
        f.timer.advance(15 * SECOND);
        assertRequestTimes(f, 0, 15000, 30000);
        f.assertMonitoring();
    }

    @Test public void disablingStateWaitsUntilTheRadioHasActuallyTurnedOff() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.DISABLING;
        f.start();
        f.timer.advance(STEP);
        assertEquals(0, f.radio.enableCalls);
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.timer.advance(STEP);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void unknownBootStateCanRecoverWithoutManualIntervention() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.UNKNOWN;
        f.start();
        f.timer.advance(2 * MINUTE);
        assertEquals(0, f.radio.enableCalls);
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.timer.advance(STEP);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void temporarilyUnavailableServiceRetriesWithBackoff() {
        Fixture f = new Fixture();
        f.radio.stateFailure = new IllegalStateException("Wi-Fi service is booting");
        f.start();
        assertEquals(1, f.radio.stateReads);
        f.timer.advance(15 * SECOND - 1);
        assertEquals(1, f.radio.stateReads);
        f.timer.advance(1);
        assertEquals(2, f.radio.stateReads);
        f.timer.advance(30 * SECOND);
        assertEquals(3, f.radio.stateReads);
        f.timer.advance(MINUTE);
        assertEquals(4, f.radio.stateReads);
        assertEquals(0, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void serviceRecoveringAfterSeveralMinutesStillEnablesWifi() {
        Fixture f = new Fixture();
        f.radio.stateFailure = new IllegalStateException("Booting");
        f.start();
        f.timer.advance(3 * MINUTE);
        f.radio.stateFailure = null;
        f.timer.advance(MINUTE);
        assertTrue(f.radio.enableCalls > 0);
        f.assertMonitoring();
    }

    @Test public void enableRuntimeFailuresBackOffAndRecoverAutomatically() {
        Fixture f = new Fixture();
        f.radio.enableFailure = new IllegalStateException("Driver is restarting");
        f.start();
        f.timer.advance(105 * SECOND);
        assertRequestTimes(f, 0, 15000, 45000, 105000);
        f.radio.enableFailure = null;
        f.timer.advance(MINUTE);
        assertEquals(5, f.radio.enableCalls);
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.timer.advance(STEP);
        f.assertMonitoring();
    }

    @Test public void securityDenialIsReportedAndRetriedAtAMinuteSpacing() {
        Fixture f = new Fixture();
        f.radio.enableFailure = new SecurityException("Denied by firmware");
        f.start();
        f.timer.advance(MINUTE - 1);
        assertEquals(1, f.radio.enableCalls);
        f.timer.advance(1);
        assertEquals(2, f.radio.enableCalls);
        f.radio.enableFailure = null;
        f.timer.advance(MINUTE);
        assertEquals(3, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void stateReadSecurityDenialDoesNotRequestEnableAndCanRecover() {
        Fixture f = new Fixture();
        f.radio.stateFailure = new SecurityException("Not ready");
        f.start();
        f.timer.advance(MINUTE - 1);
        assertEquals(1, f.radio.stateReads);
        assertEquals(0, f.radio.enableCalls);
        f.radio.stateFailure = null;
        f.timer.advance(1);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void unreadableAirplaneSettingFailsClosedUntilItBecomesReadable() {
        Fixture f = new Fixture();
        f.radio.airplaneFailure = new IllegalStateException("Settings service is booting");
        f.start();
        f.timer.advance(2 * MINUTE);
        assertEquals(0, f.radio.stateReads);
        assertEquals(0, f.radio.enableCalls);
        f.radio.airplaneFailure = null;
        f.timer.advance(MINUTE);
        assertTrue(f.radio.enableCalls > 0);
        f.assertMonitoring();
    }

    @Test public void airplaneModeSkipsRadioAccessButContinuesMonitoring() {
        Fixture f = new Fixture();
        f.radio.airplane = true;
        f.start();
        f.timer.advance(30 * SECOND - 1);
        assertEquals(1, f.radio.airplaneReads);
        assertEquals(0, f.radio.stateReads);
        assertEquals(0, f.radio.enableCalls);
        f.timer.advance(1);
        assertEquals(2, f.radio.airplaneReads);
        f.assertMonitoring();
    }

    @Test public void disablingAirplaneModeAutomaticallyRestartsWifiRecovery() {
        Fixture f = new Fixture();
        f.radio.airplane = true;
        f.start();
        f.radio.airplane = false;
        f.timer.advance(30 * SECOND);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void airplaneModeDuringRecoverySuspendsFurtherEnableRequests() {
        Fixture f = new Fixture();
        f.start();
        f.radio.airplane = true;
        f.timer.advance(2 * MINUTE);
        assertEquals(1, f.radio.enableCalls);
        f.radio.airplane = false;
        f.timer.advance(30 * SECOND);
        assertEquals(2, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void duplicateStartDoesNotCreateMultipleKeepersOrBypassCooldown() {
        Fixture f = new Fixture();
        f.start();
        for (int i = 0; i < 100; i++) f.start();
        assertEquals(1, f.radio.enableCalls);
        assertEquals(1, f.timer.pending.size());
        f.timer.advance(15 * SECOND);
        assertEquals(2, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void wakingAfterWifiTurnsOffChecksItImmediately() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.start();
        f.timer.advance(SECOND);
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.session.wake();
        assertEquals(1, f.radio.enableCalls);
        assertRequestTimes(f, 1000);
        f.assertMonitoring();
    }

    @Test public void wakeBroadcastStormCannotBypassTheEnableCooldown() {
        Fixture f = new Fixture();
        f.start();
        for (int i = 0; i < 100; i++) {
            f.timer.advance(100);
            f.session.wake();
            assertEquals(1, f.radio.enableCalls);
            assertEquals(1, f.timer.pending.size());
        }
        f.timer.advance(5 * SECOND);
        assertRequestTimes(f, 0, 15000);
        f.assertMonitoring();
    }

    @Test public void wakeBroadcastStormDoesNotHammerAnUnavailableService() {
        Fixture f = new Fixture();
        f.radio.stateFailure = new IllegalStateException("Service unavailable");
        f.start();
        for (int i = 0; i < 100; i++) {
            f.timer.advance(100);
            f.session.wake();
            assertEquals(1, f.radio.stateReads);
            assertEquals(1, f.timer.pending.size());
        }
        f.timer.advance(5 * SECOND);
        assertEquals(2, f.radio.stateReads);
        assertEquals(0, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void wakeBroadcastStormDoesNotHammerSecurityDeniedStateReads() {
        Fixture f = new Fixture();
        f.radio.stateFailure = new SecurityException("Permission denied");
        f.start();
        for (int i = 0; i < 100; i++) {
            f.timer.advance(100);
            f.session.wake();
            assertEquals(1, f.radio.stateReads);
            assertEquals(1, f.timer.pending.size());
        }
        f.timer.advance(50 * SECOND);
        assertEquals(2, f.radio.stateReads);
        assertEquals(0, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void pauseThenStartResumesAutomaticChecksWithoutExplicitRetry() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.start();
        f.session.stop();
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.timer.advance(SECOND);
        f.start();
        assertRequestTimes(f, 1000);
        f.assertMonitoring();
    }

    @Test public void stopInvalidatesEvenAnAlreadyDeliveredCallback() {
        Fixture f = new Fixture();
        f.start();
        Runnable stale = f.timer.pending.get(0).callback;
        f.session.stop();
        int reads = f.radio.stateReads;
        stale.run();
        f.session.wake();
        f.timer.advance(2 * MINUTE);
        assertEquals(1, f.radio.enableCalls);
        assertEquals(reads, f.radio.stateReads);
        f.assertStopped();
    }

    @Test public void oldCallbacksCannotJoinANewlyStartedKeeper() {
        Fixture f = new Fixture();
        f.start();
        Runnable stale = f.timer.pending.get(0).callback;
        f.session.stop();
        f.timer.advance(MINUTE);
        f.start();
        int requests = f.radio.enableCalls;
        int reads = f.radio.stateReads;
        stale.run();
        assertEquals(requests, f.radio.enableCalls);
        assertEquals(reads, f.radio.stateReads);
        f.assertMonitoring();
    }

    @Test public void explicitRestartInvalidatesOldCallbacksAndKeepsMonitoring() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.start();
        Runnable stale = f.timer.pending.get(0).callback;
        f.radio.state = WifiStartupSession.State.DISABLED;
        f.session.restart(true, true);
        int reads = f.radio.stateReads;
        assertEquals(1, f.radio.enableCalls);
        stale.run();
        assertEquals(reads, f.radio.stateReads);
        f.assertMonitoring();
    }

    @Test public void disablingThePreferenceStopsRecoveryWithoutTurningWifiOff() {
        Fixture f = new Fixture();
        f.start();
        f.session.restart(false, true);
        f.session.wake();
        f.timer.advance(2 * MINUTE);
        assertEquals(1, f.radio.enableCalls);
        assertEquals(WifiStartupSession.State.DISABLED, f.radio.state);
        f.assertStopped();
    }

    @Test public void startingWithDisabledPreferenceCancelsAnExistingKeeper() {
        Fixture f = new Fixture();
        f.start();
        f.session.start(false, true);
        f.session.wake();
        f.timer.advance(2 * MINUTE);
        assertEquals(1, f.radio.enableCalls);
        f.assertStopped();
    }

    @Test public void startingWithUnsupportedPlatformCancelsAnExistingKeeper() {
        Fixture f = new Fixture();
        f.start();
        f.session.start(true, false);
        f.session.wake();
        f.timer.advance(2 * MINUTE);
        assertEquals(1, f.radio.enableCalls);
        f.assertStopped();
    }

    @Test public void preferenceCanBeEnabledAgainWithoutRestartingTheApp() {
        Fixture f = new Fixture();
        f.session.start(false, true);
        f.session.restart(true, true);
        assertEquals(1, f.radio.enableCalls);
        f.assertMonitoring();
    }

    @Test public void healthyRadioCanBeMonitoredAllDayWithOneBoundedTimer() {
        Fixture f = new Fixture();
        f.radio.state = WifiStartupSession.State.ENABLED;
        f.start();
        f.timer.advance(24 * 60 * MINUTE);
        assertEquals(0, f.radio.enableCalls);
        assertTrue("healthy checks remain low frequency", f.radio.stateReads <= 5761);
        assertEquals(1, f.timer.maxPending);
        f.assertMonitoring();
    }

    @Test public void failingRadioKeepsRecoveringAllDayWithoutBusyLooping() {
        Fixture f = new Fixture();
        f.start();
        f.timer.advance(24 * 60 * MINUTE);
        assertTrue("recovery continues beyond the first minute", f.radio.enableCalls > 1000);
        assertTrue("backoff prevents constant driver resets", f.radio.enableCalls < 1500);
        assertEquals(1, f.timer.maxPending);
        f.assertMonitoring();
    }

    private static void assertRequestTimes(Fixture f, long... expected) {
        List<Long> times = new ArrayList<>();
        for (long value : expected) times.add(value);
        assertEquals(times, f.radio.enableTimes);
    }

    private static final class Fixture {
        final FakeTimer timer = new FakeTimer();
        final FakeRadio radio = new FakeRadio(timer);
        final List<String> messages = new ArrayList<>();
        final WifiStartupSession session = new WifiStartupSession(radio, timer, messages::add);
        void start() { session.start(true, true); }
        void assertUntouched() {
            assertEquals(0, radio.airplaneReads);
            assertEquals(0, radio.stateReads);
            assertEquals(0, radio.enableCalls);
        }
        void assertStopped() {
            assertTrue("no keeper work remains", timer.pending.isEmpty());
            assertReported();
        }
        void assertMonitoring() {
            assertEquals("exactly one next check is scheduled", 1, timer.pending.size());
            assertReported();
        }
        void assertReported() {
            assertFalse("keeper outcome is reported", messages.isEmpty());
            assertFalse(session.status().isEmpty());
            assertEquals(messages.get(messages.size() - 1), session.status());
        }
    }

    private static final class FakeRadio implements WifiStartupSession.Radio {
        final FakeTimer timer;
        final List<Long> enableTimes = new ArrayList<>();
        WifiStartupSession.State state = WifiStartupSession.State.DISABLED;
        boolean accept = true, airplane;
        int stateReads, enableCalls, airplaneReads;
        RuntimeException stateFailure, enableFailure, airplaneFailure;
        FakeRadio(FakeTimer timer) { this.timer = timer; }
        @Override public WifiStartupSession.State state() {
            stateReads++;
            if (stateFailure != null) throw stateFailure;
            return state;
        }
        @Override public boolean enable() {
            enableCalls++;
            enableTimes.add(timer.now());
            if (enableFailure != null) throw enableFailure;
            return accept;
        }
        @Override public boolean airplaneMode() {
            airplaneReads++;
            if (airplaneFailure != null) throw airplaneFailure;
            return airplane;
        }
    }

    private static final class FakeTimer implements WifiStartupSession.Timer {
        static final class Task {
            final Runnable callback;
            final long due;
            Task(Runnable callback, long due) { this.callback = callback; this.due = due; }
        }
        long clock;
        int maxPending;
        final List<Task> pending = new ArrayList<>();
        @Override public long now() { return clock; }
        @Override public void postDelayed(Runnable callback, long delay) {
            assertNotNull(callback);
            assertTrue("checks must be delayed instead of busy looping", delay > 0);
            pending.add(new Task(callback, clock + delay));
            maxPending = Math.max(maxPending, pending.size());
        }
        @Override public void remove(Runnable callback) {
            pending.removeIf(task -> task.callback == callback);
        }
        void advance(long milliseconds) {
            long target = clock + milliseconds;
            int iterations = 0;
            while (true) {
                Task next = null;
                for (Task task : pending) {
                    if (task.due <= target && (next == null || task.due < next.due)) next = task;
                }
                if (next == null) break;
                assertTrue("scheduled work stays bounded", ++iterations < 100000);
                pending.remove(next);
                clock = next.due;
                next.callback.run();
            }
            clock = target;
        }
    }
}
