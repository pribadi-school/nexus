package sch.id.pribadidepok.nexus.signage;

import java.util.function.Consumer;

/** A successful startActivity return is not proof that Android opened a screen. */
final class SettingsLaunchSession {
    interface Launcher { void open(int candidate); }
    interface Timer { void post(Runnable callback, long delay); void remove(Runnable callback); }
    private final Launcher launcher;
    private final Timer timer;
    private final Consumer<String> report;
    private boolean running, leftScreen;
    private long generation;
    private int count;
    private String label;
    private Runnable pending;

    SettingsLaunchSession(Launcher launcher, Timer timer, Consumer<String> report) {
        this.launcher = launcher; this.timer = timer; this.report = report;
    }

    boolean running() { return running; }

    void start(String name, int candidates) {
        if (running) return;
        running = true; leftScreen = false; label = name; count = candidates;
        long run = ++generation;
        report.accept("Opening " + label + "… If it does not appear, press Back or Home. No network connection is required.");
        // Let recovery draw the message before entering vendor Settings.
        pending = () -> next(0, run); timer.post(pending, 250);
    }

    private void next(int index, long run) {
        if (!running || leftScreen || run != generation) return;
        if (index >= count) {
            running = false;
            report.accept("Android could not open " + label + ". Try another Settings button. Pairing and downloaded files have not been changed.");
            return;
        }
        try { launcher.open(index); }
        catch (RuntimeException blocked) { next(index + 1, run); return; }
        if (!running || leftScreen || run != generation) return;
        // If Android silently denies the launch, this activity never pauses.
        pending = () -> next(index + 1, run); timer.post(pending, 2000);
    }

    void paused() { if (running) { leftScreen = true; removePending(); } }
    void resumed() {
        if (running && leftScreen) {
            cancel();
            report.accept("Android returned to recovery. If Settings froze or stayed invisible, try Apps settings or Google TV app settings. Wi-Fi keeper remains paused until you return to Nexus.");
        }
    }
    void cancel() { running = false; leftScreen = false; generation++; removePending(); }
    private void removePending() { if (pending != null) timer.remove(pending); pending = null; }
}
