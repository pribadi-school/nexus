<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\Database;
use App\Core\HttpException;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Optional schedules: for a time window a shared playlist plays on chosen TVs,
 * overriding what those TVs normally play. When it ends each TV is simply handed
 * its assigned playlist again, so players need no schedule logic of their own.
 * Times are school wall-clock time (Asia/Jakarta). Where schedules overlap on a
 * TV, the one that starts latest wins (the most specific), then the newest.
 */
final class SignageSchedules
{
    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** Whether a schedule row is running at this moment (pure, for tests). */
    public static function isActive(array $schedule, DateTimeImmutable $now): bool
    {
        if (!(bool) $schedule['enabled']) {
            return false;
        }
        if (((int) $schedule['days'] & (1 << ((int) $now->format('N') - 1))) === 0) {
            return false;
        }
        $date = $now->format('Y-m-d');
        if (($schedule['start_date'] ?? null) !== null && $date < $schedule['start_date']) {
            return false;
        }
        if (($schedule['end_date'] ?? null) !== null && $date > $schedule['end_date']) {
            return false;
        }
        $time = $now->format('H:i:s');
        return $time >= self::clock((string) $schedule['start_time']) && $time < self::clock((string) $schedule['end_time']);
    }

    /** The schedule running on this TV now, with its playlist's name, or null. */
    public function activeFor(int $screenId, ?DateTimeImmutable $now = null): ?array
    {
        $now ??= new DateTimeImmutable('now', local_tz());
        $running = array_filter($this->forScreen($screenId), static fn (array $s): bool => self::isActive($s, $now));
        if ($running === []) {
            return null;
        }
        usort($running, static fn (array $a, array $b): int => [$b['start_time'], $b['id']] <=> [$a['start_time'], $a['id']]);
        return $running[0];
    }

    /** Playlist ids a TV may be asked to play: its own plus any it is scheduled for. */
    public function playlistIdsFor(array $screen): array
    {
        $ids = $screen['playlist_id'] === null ? [] : [(int) $screen['playlist_id']];
        foreach ($this->forScreen((int) $screen['id']) as $schedule) {
            $ids[] = (int) $schedule['playlist_id'];
        }
        return array_values(array_unique($ids));
    }

    /** @return list<array<string,mixed>> enabled schedules for a TV */
    private function forScreen(int $screenId): array
    {
        return db()->all('SELECT s.*, p.name AS playlist_name FROM signage_schedules s
            JOIN signage_schedule_screens t ON t.schedule_id = s.id
            JOIN signage_playlists p ON p.id = s.playlist_id
            WHERE t.screen_id = ? AND s.enabled = 1', [$screenId]);
    }

    /** @return list<array<string,mixed>> every schedule, with its TVs, playlist and current state */
    public function all(?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', local_tz());
        $rows = db()->all('SELECT s.*, p.name AS playlist_name FROM signage_schedules s
            JOIN signage_playlists p ON p.id = s.playlist_id ORDER BY s.start_time, s.name, s.id');
        $screens = [];
        foreach (db()->all('SELECT t.schedule_id, sc.id, sc.name FROM signage_schedule_screens t
            JOIN signage_screens sc ON sc.id = t.screen_id ORDER BY sc.name, sc.id') as $row) {
            $screens[(int) $row['schedule_id']][] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        }
        foreach ($rows as &$row) {
            $row['screens'] = $screens[(int) $row['id']] ?? [];
            $row['state'] = self::state($row, $now);
            $row['summary'] = self::summary($row);
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        foreach ($this->all() as $schedule) {
            if ((int) $schedule['id'] === $id) {
                return $schedule;
            }
        }
        return null;
    }

    /** 'active', 'upcoming', 'ended' or 'off'. */
    public static function state(array $schedule, DateTimeImmutable $now): string
    {
        if (!(bool) $schedule['enabled']) {
            return 'off';
        }
        if (($schedule['end_date'] ?? null) !== null && $now->format('Y-m-d') > $schedule['end_date']) {
            return 'ended';
        }
        return self::isActive($schedule, $now) ? 'active' : 'upcoming';
    }

    /** "Mon–Fri · 07:00–07:30" or "Fri 23 Oct 2026 · 07:00–07:30". */
    public static function summary(array $schedule): string
    {
        $time = substr(self::clock((string) $schedule['start_time']), 0, 5) . '–' . substr(self::clock((string) $schedule['end_time']), 0, 5);
        $oneDay = ($schedule['start_date'] ?? null) !== null && $schedule['start_date'] === ($schedule['end_date'] ?? null);
        if ($oneDay) {
            return (new DateTimeImmutable($schedule['start_date']))->format('D j M Y') . ' · ' . $time;
        }
        $days = [];
        foreach (self::DAYS as $n => $label) {
            if (((int) $schedule['days'] & (1 << ($n - 1))) !== 0) {
                $days[] = $n;
            }
        }
        if (count($days) === 7) {
            $text = 'Every day';
        } elseif ($days === [1, 2, 3, 4, 5]) {
            $text = 'Mon–Fri';
        } else {
            $text = implode(', ', array_map(static fn (int $n): string => self::DAYS[$n], $days));
        }
        $range = '';
        if (($schedule['start_date'] ?? null) !== null) {
            $range .= ' from ' . (new DateTimeImmutable($schedule['start_date']))->format('j M Y');
        }
        if (($schedule['end_date'] ?? null) !== null) {
            $range .= ' until ' . (new DateTimeImmutable($schedule['end_date']))->format('j M Y');
        }
        return $text . ' · ' . $time . $range;
    }

    /**
     * Check a submitted form. Returns the row data and the TV ids, or the errors.
     * @param list<int> $knownScreens @param list<int> $sharedPlaylists
     * @return array{0:array<string,mixed>,1:list<int>,2:array<string,string>}
     */
    public static function validate(array $input, array $knownScreens, array $sharedPlaylists): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            $errors['name'] = 'Use a name of 1 to 160 characters.';
        }
        $playlist = (string) ($input['playlist_id'] ?? '');
        if (!ctype_digit($playlist) || !in_array((int) $playlist, $sharedPlaylists, true)) {
            $errors['playlist_id'] = 'Choose a shared playlist.';
        }
        $screens = [];
        foreach ((array) ($input['screen_ids'] ?? []) as $id) {
            if (is_string($id) && ctype_digit($id) && in_array((int) $id, $knownScreens, true)) {
                $screens[] = (int) $id;
            }
        }
        $screens = array_values(array_unique($screens));
        if ($screens === []) {
            $errors['screen_ids'] = 'Choose at least one TV.';
        }
        $start = self::time((string) ($input['start_time'] ?? ''));
        $end = self::time((string) ($input['end_time'] ?? ''));
        if ($start === null) {
            $errors['start_time'] = 'Enter a start time.';
        }
        if ($end === null) {
            $errors['end_time'] = 'Enter an end time.';
        } elseif ($start !== null && $end <= $start) {
            $errors['end_time'] = 'The end time must be after the start time on the same day.';
        }
        $mode = ($input['mode'] ?? 'weekly') === 'once' ? 'once' : 'weekly';
        $days = 127;
        $from = $to = null;
        if ($mode === 'once') {
            $from = $to = self::date((string) ($input['date'] ?? ''));
            if ($from === null) {
                $errors['date'] = 'Choose the date.';
            }
        } else {
            $days = 0;
            foreach ((array) ($input['days'] ?? []) as $n) {
                if (is_string($n) && ctype_digit($n) && isset(self::DAYS[(int) $n])) {
                    $days |= 1 << ((int) $n - 1);
                }
            }
            if ($days === 0) {
                $errors['days'] = 'Choose at least one weekday.';
            }
            $rawFrom = trim((string) ($input['start_date'] ?? ''));
            $rawTo = trim((string) ($input['end_date'] ?? ''));
            $from = $rawFrom === '' ? null : self::date($rawFrom);
            $to = $rawTo === '' ? null : self::date($rawTo);
            if ($rawFrom !== '' && $from === null) {
                $errors['start_date'] = 'Choose a valid date.';
            }
            if ($rawTo !== '' && $to === null) {
                $errors['end_date'] = 'Choose a valid date.';
            }
            if ($from !== null && $to !== null && $to < $from) {
                $errors['end_date'] = 'The last date cannot be before the first date.';
            }
        }
        $data = [
            'name' => $name, 'playlist_id' => (int) $playlist, 'days' => $days,
            'start_time' => $start === null ? '00:00:00' : $start . ':00', 'end_time' => $end === null ? '00:00:00' : $end . ':00',
            'start_date' => $from, 'end_date' => $to, 'enabled' => !empty($input['enabled']) ? 1 : 0,
        ];
        return [$data, $screens, $errors];
    }

    /** @param list<int> $screenIds */
    public function save(?int $id, array $data, array $screenIds): int
    {
        return db()->transaction(function () use ($id, $data, $screenIds): int {
            $playlist = db()->one('SELECT id, is_shared FROM signage_playlists WHERE id = ? FOR UPDATE', [$data['playlist_id']]);
            if ($playlist === null || !(bool) $playlist['is_shared']) {
                throw new InvalidArgumentException('Choose a shared playlist.');
            }
            $now = Database::now();
            if ($id === null) {
                $id = db()->insert('signage_schedules', $data + ['created_at' => $now, 'updated_at' => $now]);
            } else {
                if (db()->one('SELECT id FROM signage_schedules WHERE id = ? FOR UPDATE', [$id]) === null) {
                    throw HttpException::notFound('That schedule no longer exists.');
                }
                db()->update('signage_schedules', $data + ['updated_at' => $now], ['id' => $id]);
                db()->run('DELETE FROM signage_schedule_screens WHERE schedule_id = ?', [$id]);
            }
            foreach ($screenIds as $screenId) {
                db()->insert('signage_schedule_screens', ['schedule_id' => $id, 'screen_id' => $screenId]);
            }
            return $id;
        });
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        db()->update('signage_schedules', ['enabled' => $enabled ? 1 : 0, 'updated_at' => Database::now()], ['id' => $id]);
    }

    public function delete(int $id): void
    {
        db()->run('DELETE FROM signage_schedules WHERE id = ?', [$id]);
    }

    /** "07:00" (also "07:00:00" from the database) as HH:MM, or null. */
    private static function time(string $value): ?string
    {
        return preg_match('/\A([01][0-9]|2[0-3]):([0-5][0-9])(?::[0-5][0-9])?\z/D', trim($value), $m) ? $m[1] . ':' . $m[2] : null;
    }

    private static function date(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        return $date !== false && $date->format('Y-m-d') === trim($value) ? trim($value) : null;
    }

    private static function clock(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
