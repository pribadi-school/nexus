package sch.id.pribadidepok.nexus.rescue;

import org.junit.Test;
import static org.junit.Assert.*;
import java.util.ArrayList;
import java.util.List;

public final class AsyncChecksTest {
    private static final class Screen implements AsyncChecks.Ui {
        final List<Runnable> immediate = new ArrayList<>(), later = new ArrayList<>();
        @Override public void post(Runnable task, long delay) { (delay == 0 ? immediate : later).add(task); }
        @Override public void remove(Runnable task) { immediate.remove(task); later.remove(task); }
        void finish() { while (!immediate.isEmpty()) immediate.remove(0).run(); }
    }
    @Test public void blockedWifiDoesNotPreventIndependentChecksOrTimeoutFeedback() {
        List<Runnable> work = new ArrayList<>(); Screen screen = new Screen(); List<String> output = new ArrayList<>();
        AsyncChecks checks = new AsyncChecks(work::add, screen);
        assertTrue(checks.run("wifi", () -> "wifi finished", output::add));
        assertTrue(checks.run("network", () -> "network finished", output::add));
        work.get(1).run(); screen.finish();
        assertEquals("network finished", output.get(0));
        screen.later.get(0).run();
        assertTrue(output.get(1).contains("has not answered"));
        assertFalse(checks.run("wifi", () -> "duplicate", output::add));
    }
    @Test public void timeoutDoesNotClaimCancellationAndLateResponseIsDelivered() {
        List<Runnable> work = new ArrayList<>(); Screen screen = new Screen(); List<String> output = new ArrayList<>();
        AsyncChecks checks = new AsyncChecks(work::add, screen);
        checks.run("action", () -> "Android accepted request", output::add);
        screen.later.get(0).run();
        assertTrue(output.get(0).contains("request remains pending"));
        work.get(0).run(); screen.finish();
        assertEquals("Android accepted request", output.get(1));
        assertTrue(screen.later.isEmpty());
    }
    @Test public void oldCompletionCannotOverwriteNewAction() {
        List<Runnable> work = new ArrayList<>(); Screen screen = new Screen(); List<String> output = new ArrayList<>();
        AsyncChecks checks = new AsyncChecks(work::add, screen);
        checks.run("action", () -> "first", output::add);
        work.get(0).run();
        assertFalse(checks.run("action", () -> "second", output::add));
        screen.finish();
        assertTrue(checks.run("action", () -> "second", output::add));
        work.get(1).run(); screen.finish();
        assertEquals(java.util.Arrays.asList("first", "second"), output);
    }
    @Test public void closeSuppressesTimeoutAndLateResults() {
        List<Runnable> work = new ArrayList<>(); Screen screen = new Screen(); List<String> output = new ArrayList<>();
        AsyncChecks checks = new AsyncChecks(work::add, screen);
        checks.run("wifi", () -> "finished", output::add);
        Runnable oldTimer = screen.later.get(0);
        checks.close(); oldTimer.run(); work.get(0).run(); screen.finish();
        assertTrue(output.isEmpty());
        assertFalse(checks.run("network", () -> "new check", output::add));
    }
    @Test public void vendorExceptionBecomesVisibleAndAllowsRetry() {
        List<Runnable> work = new ArrayList<>(); Screen screen = new Screen(); List<String> output = new ArrayList<>();
        AsyncChecks checks = new AsyncChecks(work::add, screen);
        checks.run("action", () -> { throw new SecurityException("permission denied"); }, output::add);
        work.get(0).run(); screen.finish();
        assertTrue(output.get(0).contains("SecurityException: permission denied"));
        assertTrue(checks.run("action", () -> "retry", output::add));
    }
    @Test public void closingBeforeWorkerStartsPreventsQueuedWifiChange() {
        List<Runnable> work = new ArrayList<>(); Screen screen = new Screen();
        java.util.concurrent.atomic.AtomicBoolean changed = new java.util.concurrent.atomic.AtomicBoolean();
        AsyncChecks checks = new AsyncChecks(work::add, screen);
        checks.run("action", () -> { changed.set(true); return "requested"; }, result -> fail("Closed screen received result"));
        checks.close(); work.get(0).run(); screen.finish();
        assertFalse(changed.get());
    }
    @Test public void executorRejectionIsVisibleAndDoesNotLeaveKeyBusy() {
        Screen screen = new Screen(); List<String> output = new ArrayList<>();
        AsyncChecks checks = new AsyncChecks(task -> { throw new java.util.concurrent.RejectedExecutionException(); }, screen);
        assertFalse(checks.run("wifi", () -> "unused", output::add));
        assertTrue(output.get(0).contains("Could not start"));
        assertTrue(screen.later.isEmpty());
        assertFalse(checks.run("wifi", () -> "unused", output::add));
        assertEquals(2, output.size());
    }
}
