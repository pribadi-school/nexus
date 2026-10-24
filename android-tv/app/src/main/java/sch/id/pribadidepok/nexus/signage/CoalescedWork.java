package sch.id.pribadidepok.nexus.signage;

import java.util.concurrent.Executor;
import java.util.concurrent.atomic.AtomicBoolean;

/** At most one queued/running wake check, even when vendor Wi-Fi IPC stalls. */
final class CoalescedWork implements AutoCloseable {
    private final Executor executor;
    private final Runnable work;
    private final AtomicBoolean pending = new AtomicBoolean();
    private final AtomicBoolean closed = new AtomicBoolean();

    CoalescedWork(Executor executor, Runnable work) { this.executor = executor; this.work = work; }

    void request() {
        if (closed.get() || !pending.compareAndSet(false, true)) return;
        try {
            executor.execute(() -> {
                try { if (!closed.get()) work.run(); }
                finally { pending.set(false); }
            });
        } catch (RuntimeException rejected) { pending.set(false); throw rejected; }
    }

    @Override public void close() { closed.set(true); }
}
