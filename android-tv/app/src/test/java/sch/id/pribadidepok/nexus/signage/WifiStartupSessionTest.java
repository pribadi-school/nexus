package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import java.util.ArrayList;
import java.util.List;
import static org.junit.Assert.*;

public class WifiStartupSessionTest {
    private static final long STEP = 5000;
    private static final long WINDOW = 60000;

    @Test public void disabledPreferenceDoesNotTouchTheRadio() {
        Fixture fixture = new Fixture();
        fixture.session.start(false, true);
        fixture.timer.advance(WINDOW * 2);
        fixture.assertUntouched();
        fixture.assertFinished();
    }

    @Test public void unsupportedAndroidDoesNotTouchTheRadio() {
        Fixture fixture = new Fixture();
        fixture.session.start(true, false);
        fixture.timer.advance(WINDOW * 2);
        fixture.assertUntouched();
        fixture.assertFinished();
    }

    @Test public void airplaneModeSkipsStateReadAndEnable() {
        Fixture fixture = new Fixture();
        fixture.radio.airplane = true;
        fixture.start();
        assertEquals(1, fixture.radio.airplaneReads);
        assertEquals(0, fixture.radio.stateReads);
        assertEquals(0, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void alreadyEnabledRadioIsLeftAlone() {
        Fixture fixture = new Fixture();
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.start();
        assertEquals(0, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void disabledRadioReceivesOneRequestThenCompletesAsynchronously() {
        Fixture fixture = new Fixture();
        fixture.start();
        assertEquals(1, fixture.radio.enableCalls);
        assertEquals(1, fixture.timer.pending.size());
        fixture.radio.state = WifiStartupSession.State.ENABLING;
        fixture.timer.advance(STEP);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void initialEnablingStateNeverReceivesAnotherEnableRequest() {
        Fixture fixture = new Fixture();
        fixture.radio.state = WifiStartupSession.State.ENABLING;
        fixture.start();
        fixture.timer.advance(STEP * 3);
        assertEquals(0, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        fixture.assertFinished();
    }

    @Test public void initialEnablingThenDisabledRespectsTheOffChange() {
        Fixture fixture = new Fixture();
        fixture.radio.state = WifiStartupSession.State.ENABLING;
        fixture.start();
        fixture.radio.state = WifiStartupSession.State.DISABLED;
        fixture.timer.advance(WINDOW * 2);
        assertEquals(0, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void acceptedRequestIsNotRepeatedWhenRadioRemainsOff() {
        Fixture fixture = new Fixture();
        fixture.start();
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void acceptedRequestWaitsForASlowDriverWithoutRepeatingEnable() {
        Fixture fixture = new Fixture();
        fixture.start();
        fixture.timer.advance(STEP * 3);
        assertEquals(1, fixture.radio.enableCalls);
        assertEquals("slow accepted startup remains pending", 1, fixture.timer.pending.size());
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void acceptedRequestThenEnablingThenOffDoesNotFightManualDisable() {
        Fixture fixture = new Fixture();
        fixture.start();
        fixture.radio.state = WifiStartupSession.State.ENABLING;
        fixture.timer.advance(STEP);
        fixture.radio.state = WifiStartupSession.State.DISABLED;
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void successfulStartupDoesNotMonitorOrReenableLaterManualOff() {
        Fixture fixture = new Fixture();
        fixture.start();
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        int reads = fixture.radio.stateReads;
        fixture.radio.state = WifiStartupSession.State.DISABLED;
        fixture.session.start(true, true);
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.enableCalls);
        assertEquals(reads, fixture.radio.stateReads);
        fixture.assertFinished();
    }

    @Test public void disablingRadioWaitsUntilDisabledBeforeRequestingEnable() {
        Fixture fixture = new Fixture();
        fixture.radio.state = WifiStartupSession.State.DISABLING;
        fixture.start();
        fixture.timer.advance(STEP);
        assertEquals(0, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.DISABLED;
        fixture.timer.advance(STEP);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        fixture.assertFinished();
    }

    @Test public void unknownBootStateCanRecoverWithinTheStartupWindow() {
        Fixture fixture = new Fixture();
        fixture.radio.state = WifiStartupSession.State.UNKNOWN;
        fixture.start();
        assertEquals(0, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.DISABLED;
        fixture.timer.advance(STEP);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        fixture.assertFinished();
    }

    @Test public void unavailableServiceRetriesButStopsAtOneMinute() {
        Fixture fixture = new Fixture();
        fixture.radio.stateFailure = new IllegalStateException("Wi-Fi service unavailable");
        fixture.start();
        fixture.timer.advance(WINDOW);
        assertEquals(0, fixture.radio.enableCalls);
        assertTrue(fixture.radio.stateReads > 1);
        int reads = fixture.radio.stateReads;
        fixture.timer.advance(WINDOW * 2);
        assertEquals(reads, fixture.radio.stateReads);
        fixture.assertFinished();
    }

    @Test public void transientServiceFailureDoesNotPreventSuccessfulStartup() {
        Fixture fixture = new Fixture();
        fixture.radio.stateFailure = new IllegalStateException("Booting");
        fixture.start();
        fixture.radio.stateFailure = null;
        fixture.timer.advance(STEP);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        fixture.assertFinished();
    }

    @Test public void rejectedRequestsHaveFiveSecondSpacingAndAThreeRequestLimit() {
        Fixture fixture = new Fixture();
        fixture.radio.accept = false;
        fixture.start();
        fixture.timer.advance(STEP - 1);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.timer.advance(1);
        assertEquals(2, fixture.radio.enableCalls);
        fixture.timer.advance(STEP);
        assertEquals(3, fixture.radio.enableCalls);
        fixture.timer.advance(WINDOW * 2);
        assertEquals(3, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void runtimeFailureWhileEnablingCannotExceedThreeRequests() {
        Fixture fixture = new Fixture();
        fixture.radio.enableFailure = new IllegalStateException("Driver is restarting");
        fixture.start();
        fixture.timer.advance(WINDOW * 2);
        assertEquals(3, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void securityDenialStopsImmediatelyWithoutRepeatedRequests() {
        Fixture fixture = new Fixture();
        fixture.radio.enableFailure = new SecurityException("Denied by firmware");
        fixture.start();
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void stateReadSecurityDenialNeverAttemptsEnable() {
        Fixture fixture = new Fixture();
        fixture.radio.stateFailure = new SecurityException("State access denied");
        fixture.start();
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.stateReads);
        assertEquals(0, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void unreadableAirplaneSettingFailsClosedWithinTheTimeLimit() {
        Fixture fixture = new Fixture();
        fixture.radio.airplaneFailure = new IllegalStateException("Settings service unavailable");
        fixture.start();
        fixture.timer.advance(WINDOW * 2);
        assertEquals(0, fixture.radio.stateReads);
        assertEquals(0, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void airplaneModeDuringRetryStopsFurtherEnableRequests() {
        Fixture fixture = new Fixture();
        fixture.radio.accept = false;
        fixture.start();
        fixture.radio.airplane = true;
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void stuckEnablingRadioTimesOutWithoutRepeatedRequests() {
        Fixture fixture = new Fixture();
        fixture.start();
        fixture.radio.state = WifiStartupSession.State.ENABLING;
        fixture.timer.advance(WINDOW - 1);
        assertEquals(1, fixture.timer.pending.size());
        fixture.timer.advance(1);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.assertFinished();
    }

    @Test public void duplicateStartDoesNotCreateMultipleRetryLoops() {
        Fixture fixture = new Fixture();
        fixture.radio.accept = false;
        fixture.start();
        fixture.session.start(true, true);
        fixture.session.start(true, true);
        assertEquals(1, fixture.radio.enableCalls);
        assertEquals(1, fixture.timer.pending.size());
        fixture.timer.advance(STEP);
        assertEquals(2, fixture.radio.enableCalls);
        assertEquals(1, fixture.timer.pending.size());
    }

    @Test public void stopRemovesPendingWorkAndInvalidatesEvenAnAlreadyDeliveredCallback() {
        Fixture fixture = new Fixture();
        fixture.radio.accept = false;
        fixture.start();
        Runnable oldCallback = fixture.timer.pending.get(0).callback;
        fixture.session.stop();
        int reads = fixture.radio.stateReads;
        oldCallback.run();
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.enableCalls);
        assertEquals(reads, fixture.radio.stateReads);
        fixture.assertFinished();
    }

    @Test public void explicitRetryRestartsButOldCallbacksCannotJoinTheNewSession() {
        Fixture fixture = new Fixture();
        fixture.radio.accept = false;
        fixture.start();
        Runnable oldCallback = fixture.timer.pending.get(0).callback;
        fixture.session.restart(true, true);
        assertEquals(2, fixture.radio.enableCalls);
        oldCallback.run();
        assertEquals(2, fixture.radio.enableCalls);
        assertEquals(1, fixture.timer.pending.size());
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        fixture.assertFinished();
    }

    @Test public void turningTheFeatureOffCancelsRetryWithoutChangingRadioState() {
        Fixture fixture = new Fixture();
        fixture.radio.accept = false;
        fixture.start();
        fixture.session.restart(false, true);
        fixture.timer.advance(WINDOW * 2);
        assertEquals(1, fixture.radio.enableCalls);
        assertEquals(WifiStartupSession.State.DISABLED, fixture.radio.state);
        fixture.assertFinished();
    }

    @Test public void explicitRetryCanEnableAfterAPreviouslyCompletedStartup() {
        Fixture fixture = new Fixture();
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.start();
        fixture.radio.state = WifiStartupSession.State.DISABLED;
        fixture.session.restart(true, true);
        assertEquals(1, fixture.radio.enableCalls);
        fixture.radio.state = WifiStartupSession.State.ENABLED;
        fixture.timer.advance(STEP);
        fixture.assertFinished();
    }

    private static final class Fixture {
        final FakeRadio radio = new FakeRadio();
        final FakeTimer timer = new FakeTimer();
        final List<String> messages = new ArrayList<>();
        final WifiStartupSession session = new WifiStartupSession(radio, timer, messages::add);
        void start() { session.start(true, true); }
        void assertUntouched() {
            assertEquals(0, radio.airplaneReads);
            assertEquals(0, radio.stateReads);
            assertEquals(0, radio.enableCalls);
        }
        void assertFinished() {
            assertTrue("no background retry loop remains", timer.pending.isEmpty());
            assertFalse("startup outcome is reported", messages.isEmpty());
            assertFalse(session.status().isEmpty());
            assertEquals(messages.get(messages.size() - 1), session.status());
        }
    }

    private static final class FakeRadio implements WifiStartupSession.Radio {
        WifiStartupSession.State state = WifiStartupSession.State.DISABLED;
        boolean accept = true, airplane;
        int stateReads, enableCalls, airplaneReads;
        RuntimeException stateFailure, enableFailure, airplaneFailure;
        @Override public WifiStartupSession.State state() {
            stateReads++;
            if (stateFailure != null) throw stateFailure;
            return state;
        }
        @Override public boolean enable() {
            enableCalls++;
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
        final List<Task> pending = new ArrayList<>();
        @Override public long now() { return clock; }
        @Override public void postDelayed(Runnable callback, long delay) {
            assertNotNull(callback);
            assertTrue("retry is delayed instead of a busy loop", delay > 0);
            pending.add(new Task(callback, clock + delay));
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
                assertTrue("scheduled work stays bounded", ++iterations < 1000);
                pending.remove(next);
                clock = next.due;
                next.callback.run();
            }
            clock = target;
        }
    }
}
