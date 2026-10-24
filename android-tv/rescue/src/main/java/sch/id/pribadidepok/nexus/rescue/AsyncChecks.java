package sch.id.pribadidepok.nexus.rescue;

import java.util.Map;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.Executor;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.function.Consumer;

/** Slow vendor calls never run on the screen thread. A timeout does not cancel Binder work. */
final class AsyncChecks {
    interface Task { String run() throws Exception; }
    interface Ui { void post(Runnable task, long delay); void remove(Runnable task); }
    private final Executor worker;
    private final Ui ui;
    private final Set<String> active = ConcurrentHashMap.newKeySet();
    private final Map<String, Runnable> timers = new ConcurrentHashMap<>();
    private final AtomicBoolean closed = new AtomicBoolean();

    AsyncChecks(Executor worker, Ui ui) { this.worker = worker; this.ui = ui; }

    boolean run(String key, Task task, Consumer<String> report) {
        if (closed.get() || !active.add(key)) return false;
        AtomicBoolean finished = new AtomicBoolean();
        Runnable overdue = () -> {
            if (!closed.get() && !finished.get()) report.accept(
                "Android has not answered after 8 seconds. You can still use this app. "
                + "The request remains pending; this does not prove Wi-Fi is off.");
        };
        timers.put(key, overdue);
        ui.post(overdue, 8000);
        try {
            worker.execute(() -> {
                if (closed.get()) { finished.set(true); active.remove(key); return; }
                String result;
                try { result = task.run(); }
                catch (Exception failure) { result = "Android reported " + failure.getClass().getSimpleName() + ": " + failure.getMessage(); }
                finished.set(true);
                String message = result;
                // Keep the key busy until its completion is delivered, so an old
                // result cannot overwrite the next click's status.
                ui.post(() -> {
                    ui.remove(overdue); timers.remove(key, overdue); active.remove(key);
                    if (!closed.get()) report.accept(message);
                }, 0);
            });
        } catch (RuntimeException rejected) {
            active.remove(key); timers.remove(key, overdue); ui.remove(overdue);
            if (!closed.get()) report.accept("Could not start this check. Close and reopen Network Rescue.");
            return false;
        }
        return true;
    }

    void close() {
        closed.set(true);
        for (Runnable timer : timers.values()) ui.remove(timer);
        timers.clear();
    }
}
