package sch.id.pribadidepok.nexus.signage;

import org.junit.Test;
import java.util.ArrayList;
import java.util.List;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;
import static org.junit.Assert.*;

public class CoalescedWorkTest {
    @Test(timeout = 5000) public void blockedRadioDoesNotBlockCallerOrQueueAWakeStorm() throws Exception {
        ExecutorService executor = Executors.newSingleThreadExecutor();
        CountDownLatch entered = new CountDownLatch(1), release = new CountDownLatch(1);
        AtomicInteger calls = new AtomicInteger();
        Thread caller = Thread.currentThread();
        CoalescedWork work = new CoalescedWork(executor, () -> {
            assertNotSame(caller, Thread.currentThread()); calls.incrementAndGet(); entered.countDown();
            try { release.await(); } catch (InterruptedException stopped) { Thread.currentThread().interrupt(); }
        });
        try {
            work.request(); assertTrue(entered.await(2, TimeUnit.SECONDS));
            for (int i = 0; i < 10000; i++) work.request();
            assertEquals(1, calls.get());
            work.close(); release.countDown(); executor.shutdown();
            assertTrue(executor.awaitTermination(2, TimeUnit.SECONDS));
            assertEquals(1, calls.get());
        } finally { work.close(); release.countDown(); executor.shutdownNow(); }
    }

    @Test public void queuedWakeChecksCoalesceAndNextRequestCanRunAfterCompletion() {
        List<Runnable> queue = new ArrayList<>(); AtomicInteger calls = new AtomicInteger();
        CoalescedWork work = new CoalescedWork(queue::add, calls::incrementAndGet);
        for (int i = 0; i < 1000; i++) work.request();
        assertEquals(1, queue.size()); queue.remove(0).run(); assertEquals(1, calls.get());
        work.request(); assertEquals(1, queue.size()); queue.remove(0).run(); assertEquals(2, calls.get());
    }

    @Test public void shutdownInvalidatesAnAlreadyQueuedCheck() {
        List<Runnable> queue = new ArrayList<>(); AtomicInteger calls = new AtomicInteger();
        CoalescedWork work = new CoalescedWork(queue::add, calls::incrementAndGet);
        work.request(); work.close(); queue.remove(0).run(); work.request();
        assertEquals(0, calls.get()); assertTrue(queue.isEmpty());
    }

    @Test public void failedWorkDoesNotPermanentlyBlockLaterWakeChecks() {
        List<Runnable> queue = new ArrayList<>(); AtomicInteger calls = new AtomicInteger();
        CoalescedWork work = new CoalescedWork(queue::add, () -> { if (calls.incrementAndGet() == 1) throw new IllegalStateException("vendor"); });
        work.request(); assertThrows(IllegalStateException.class, () -> queue.remove(0).run());
        work.request(); queue.remove(0).run(); assertEquals(2, calls.get());
    }

    @Test public void executorRejectionDoesNotLeavePendingFlagStuck() {
        List<Runnable> queue = new ArrayList<>(); AtomicInteger submissions = new AtomicInteger();
        CoalescedWork work = new CoalescedWork(callback -> {
            if (submissions.incrementAndGet() == 1) throw new IllegalStateException("stopped");
            queue.add(callback);
        }, () -> {});
        assertThrows(IllegalStateException.class, work::request); work.request(); assertEquals(1, queue.size());
    }
}
