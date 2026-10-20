package sch.id.pribadidepok.nexus.signage;

import java.util.function.Consumer;

/** Persistent radio keeper, with one timer and a capped cooldown between requests. */
final class WifiStartupSession {
    enum State { DISABLED, DISABLING, ENABLING, ENABLED, UNKNOWN }
    interface Radio {
        State state();
        boolean enable();
        boolean airplaneMode();
    }
    interface Timer {
        long now();
        void postDelayed(Runnable callback, long delay);
        void remove(Runnable callback);
    }
    private static final long CHECK_MS = 5000;
    private static final long HEALTHY_MS = 15000;
    private static final long AIRPLANE_MS = 30000;
    private static final long MAX_RETRY_MS = 60000;
    private final Radio radio;
    private final Timer timer;
    private final Consumer<String> report;
    private boolean running;
    private int requests, serviceFailures;
    private long nextEnableAt, blockedUntil, generation;
    private Runnable pending;
    private String status = "Wi-Fi keeper has not started yet.";

    WifiStartupSession(Radio radio, Timer timer, Consumer<String> report) {
        this.radio = radio; this.timer = timer; this.report = report;
    }

    String status() { return status; }

    void start(boolean enabled, boolean supported) {
        if (!enabled || !supported) {
            stop();
            update(!enabled ? "Wi-Fi keeper is off."
                : "This Android version requires Wi-Fi to be enabled in System settings.");
            return;
        }
        if (running) return;
        running = true; requests = 0; serviceFailures = 0;
        nextEnableAt = 0; blockedUntil = 0;
        long run = ++generation;
        pending = () -> check(run);
        check(run);
    }

    void restart(boolean enabled, boolean supported) {
        stop(); start(enabled, supported);
    }

    /** Wake and radio broadcasts can advance a check, but never the request cooldown. */
    void wake() {
        if (!running) return;
        timer.remove(pending);
        check(generation);
    }

    void stop() {
        boolean interrupted = running;
        running = false; generation++;
        if (pending != null) timer.remove(pending);
        pending = null;
        if (interrupted) update("Wi-Fi keeper stopped.");
    }

    private void check(long run) {
        if (!running || run != generation) return;
        long delay = CHECK_MS;
        long now = timer.now();
        if (now < blockedUntil) {
            queue(run, blockedUntil - now); return;
        }
        try {
            if (radio.airplaneMode()) {
                update("Airplane mode is on. Wi-Fi keeper will resume when it is off.");
                delay = AIRPLANE_MS;
            } else {
                State state = radio.state();
                if (state == State.ENABLED) {
                    requests = 0;
                    update("Wi-Fi is on. Keeper is active; Android connects to its saved network.");
                    delay = HEALTHY_MS;
                } else if (state == State.DISABLED) {
                    if (now >= nextEnableAt) {
                        // Establish the cooldown before calling Android: broadcasts or
                        // accepted-but-failed asynchronous requests cannot bypass it.
                        requests = Math.min(3, requests + 1);
                        nextEnableAt = now + retryDelay(requests);
                        boolean accepted = radio.enable();
                        update(accepted
                            ? "Wi-Fi turn-on requested. Recovery will retry automatically if needed."
                            : "Android is not ready for Wi-Fi. Keeper will retry automatically.");
                    } else update("Waiting for Wi-Fi. Automatic recovery is active.");
                } else if (state == State.ENABLING) {
                    update("Android is turning Wi-Fi on. Keeper is active.");
                } else update("Waiting for Android's Wi-Fi service. Keeper is active.");
                serviceFailures = 0;
            }
        } catch (SecurityException denied) {
            blockedUntil = now + MAX_RETRY_MS;
            delay = MAX_RETRY_MS;
            update("Android blocked Wi-Fi control. Keeper will check again; see System settings.");
        } catch (RuntimeException unavailable) {
            serviceFailures = Math.min(3, serviceFailures + 1);
            delay = retryDelay(serviceFailures);
            blockedUntil = now + delay;
            update("Wi-Fi service is not ready. Keeper will retry automatically.");
        }
        queue(run, delay);
    }

    private static long retryDelay(int attempt) { return 15000L << Math.min(2, attempt - 1); }

    private void queue(long run, long delay) {
        if (running && run == generation) {
            timer.remove(pending);
            timer.postDelayed(pending, delay);
        }
    }

    private void update(String message) {
        if (message.equals(status)) return;
        status = message; report.accept(message);
    }
}
