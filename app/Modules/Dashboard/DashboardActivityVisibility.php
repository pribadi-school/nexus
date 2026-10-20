<?php
declare(strict_types=1);

namespace App\Modules\Dashboard;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/** A reversible dashboard preference. Device notes and the full activity feed stay intact. */
final class DashboardActivityVisibility
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? BASE_PATH . '/storage/private/dashboard-activity.json';
    }

    public function since(): ?string
    {
        try { return $this->read()['since_utc'] ?? null; }
        // A missing/broken preference must never prevent opening the dashboard.
        catch (Throwable) { return null; }
    }

    public function configure(bool $apply = false): array
    {
        $setting = $this->read();
        $new = $setting === null;
        if ($setting === null) {
            $boards = db()->all("SELECT d.id, r.name FROM devices d
                JOIN studytouch_boards b ON b.device_id=d.id JOIN rooms r ON r.id=b.room_id
                WHERE d.category='studytouch' AND d.deleted_at IS NULL
                AND UPPER(REPLACE(r.name, ' ', '')) IN ('12B','XIIB','GRADE12B','GRADEXIIB')");
            if (count($boards) !== 1) throw new RuntimeException('Expected exactly one active Grade 12B StudyTouch. Dashboard visibility was not changed.');
            $notes = db()->all("SELECT id, kind, meta, created_at FROM device_notes
                WHERE device_id=? AND kind IN ('problem','status') ORDER BY created_at DESC, id DESC", [$boards[0]['id']]);
            $problem = null;
            foreach ($notes as $note) {
                $meta = json_decode((string) ($note['meta'] ?? ''), true);
                if ($note['kind'] === 'problem' || ($meta['to'] ?? null) === 'problem') { $problem = $note; break; }
            }
            if ($problem === null) throw new RuntimeException('No Grade 12B problem entry was found. Dashboard visibility was not changed.');
            $setting = ['format' => 1, 'database' => $this->fingerprint(), 'room' => $boards[0]['name'],
                'device_id' => (int) $boards[0]['id'], 'problem_note_id' => (int) $problem['id'], 'since_utc' => $problem['created_at']];
        }
        $summary = ['mode' => $apply ? 'applied' : 'preview', 'kept_room' => $setting['room'],
            'problem_note_id' => $setting['problem_note_id'], 'dashboard_activity_from_utc' => $setting['since_utc'],
            'older_dashboard_entries_hidden' => (int) db()->value('SELECT COUNT(*) FROM device_notes n
                JOIN devices d ON d.id=n.device_id WHERE d.deleted_at IS NULL AND n.created_at < ?', [$setting['since_utc']]),
            'device_history_changed' => false, 'all_activity_page_changed' => false];
        if ($apply && $new) $this->write($setting);
        return $summary;
    }

    public function reset(): void
    {
        if (is_link($this->path)) throw new RuntimeException('Refusing a linked dashboard preference.');
        if (is_file($this->path) && !unlink($this->path)) throw new RuntimeException('Could not reset dashboard visibility.');
    }

    private function read(): ?array
    {
        if (!file_exists($this->path) && !is_link($this->path)) return null;
        if (is_link($this->path) || !is_file($this->path)) throw new RuntimeException('Invalid dashboard preference file.');
        $setting = json_decode((string) file_get_contents($this->path), true, 512, JSON_THROW_ON_ERROR);
        $since = $setting['since_utc'] ?? null;
        $date = is_string($since) ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $since, new DateTimeZone('UTC')) : false;
        if (($setting['format'] ?? null) !== 1 || ($setting['database'] ?? null) !== $this->fingerprint()
            || !$date || $date->format('Y-m-d H:i:s') !== $since
            || !is_int($setting['problem_note_id'] ?? null) || !is_int($setting['device_id'] ?? null) || !is_string($setting['room'] ?? null)) {
            throw new RuntimeException('Invalid dashboard preference for this database.');
        }
        return $setting;
    }

    private function write(array $setting): void
    {
        if (is_link(dirname($this->path)) || !is_dir(dirname($this->path)) || is_link($this->path)) {
            throw new RuntimeException('A private preference folder is required.');
        }
        $temporary = $this->path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $handle = fopen($temporary, 'x');
        if ($handle === false) throw new RuntimeException('Could not create the dashboard preference.');
        try {
            if (PHP_OS_FAMILY !== 'Windows' && !chmod($temporary, 0600)) throw new RuntimeException('Could not protect the dashboard preference.');
            $json = json_encode($setting, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('Could not save dashboard visibility.');
            fclose($handle); $handle = null;
            if (!rename($temporary, $this->path)) throw new RuntimeException('Could not apply dashboard visibility.');
        } finally {
            if (is_resource($handle)) fclose($handle);
            if (is_file($temporary)) unlink($temporary);
        }
    }

    private function fingerprint(): string
    {
        return hash('sha256', config('database.host') . ':' . config('database.port') . '/' . config('database.name'));
    }
}
