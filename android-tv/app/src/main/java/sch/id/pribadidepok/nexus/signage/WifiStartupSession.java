package sch.id.pribadidepok.nexus.signage;

import java.util.function.Consumer;

/** One bounded startup attempt; never a background loop fighting manual Wi-Fi changes. */
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
    private static final long WINDOW_MS = 60000;
    private static final long RETRY_MS = 5000;
    private final Radio radio;
    private final Timer timer;
    private final Consumer<String> report;
    private boolean started, running, accepted, enablingSeen;
    private int requests;
    private long deadline, generation;
    private Runnable pending;
    private String status = "Wi-Fi startup has not run yet.";

    WifiStartupSession(Radio radio, Timer timer, Consumer<String> report) {
        this.radio = radio; this.timer = timer; this.report = report;
    }

    String status() { return status; }

    void start(boolean enabled, boolean supported) {
        if (started) return;
        started = true;
        if (!enabled) { update("Automatic Wi-Fi startup is off."); return; }
        if (!supported) { update("This Android version requires Wi-Fi to be enabled in System settings."); return; }
        running = true; accepted = false; enablingSeen = false; requests = 0;
        deadline = timer.now() + WINDOW_MS;
        long run = ++generation;
        pending = () -> check(run);
        check(run);
    }

    void restart(boolean enabled, boolean supported) {
        stop(); started = false; start(enabled, supported);
    }

    void stop() {
        boolean interrupted = running;
        running = false; generation++;
        if (pending != null) timer.remove(pending);
        pending = null;
        if (interrupted) update("Wi-Fi startup check paused. Use Wi-Fi startup settings to try again.");
    }

    private void check(long run) {
        if (!running || run != generation) return;
        try {
            if (radio.airplaneMode()) { finish("Airplane mode is on. Wi-Fi startup was skipped."); return; }
            State state = radio.state();
            if (state == State.ENABLED) { finish("Wi-Fi is on. Android manages the saved network connection."); return; }
            if (timer.now() >= deadline) { finish("Wi-Fi did not turn on within one minute. Check System settings or the display firmware."); return; }
            if (state == State.DISABLED) {
                // A successful request is asynchronous. Do not fight a later OFF
                // change or keep cycling a failing driver back on indefinitely.
                if (enablingSeen) { finish("Wi-Fi is off again. Check System settings before retrying."); return; }
                if (accepted) {
                    // Boot can delay the first DISABLED -> ENABLING transition.
                    // Wait for it without sending a second accepted request.
                    update("Waiting for Android to enable Wi-Fi…");
                } else {
                    if (requests >= 3) { finish("Android did not accept the Wi-Fi startup request. Check System settings."); return; }
                    requests++;
                    accepted = radio.enable();
                    update(accepted ? "Wi-Fi turn-on requested. Waiting for Android…" : "Wi-Fi is not ready. Retrying startup…");
                }
            } else if (state == State.ENABLING) {
                enablingSeen = true;
                update("Android is turning Wi-Fi on…");
            } else update("Waiting for Android's Wi-Fi service…");
        } catch (SecurityException denied) {
            finish("Android blocked Wi-Fi control. Enable Wi-Fi in System settings."); return;
        } catch (RuntimeException unavailable) {
            // Service restarts can happen during boot; the time window remains bounded.
            update("Wi-Fi service is not ready. Retrying startup…");
            if (timer.now() >= deadline || requests >= 3) { finish("Wi-Fi startup could not finish. Check System settings."); return; }
        }
        if (running && run == generation) timer.postDelayed(pending, RETRY_MS);
    }

    private void finish(String message) {
        running = false;
        if (pending != null) timer.remove(pending);
        update(message);
    }

    private void update(String message) { status = message; report.accept(message); }
}
