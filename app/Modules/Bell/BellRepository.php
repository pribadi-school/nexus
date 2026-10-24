<?php

declare(strict_types=1);

namespace App\Modules\Bell;

use App\Core\Database;
use InvalidArgumentException;

/**
 * Everything the Bell pages and the bell PC read and write. Each zone owns its
 * profiles, entries, holidays and volume, so a change in one zone never
 * touches another; only the sound library and the PC key are shared.
 */
final class BellRepository
{
    /** The PC syncs about every 30 seconds; this long without one means offline. */
    public const ONLINE_SECONDS = 120;

    /** @return list<array<string,mixed>> */
    public function zones(): array
    {
        return db()->all('SELECT * FROM bell_zones ORDER BY sort_order, id');
    }

    /** @return array<string,mixed>|null */
    public function zone(int $id): ?array
    {
        return db()->one('SELECT * FROM bell_zones WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function profiles(int $zoneId): array
    {
        return db()->all(
            'SELECT p.*, (SELECT COUNT(*) FROM bell_entries e WHERE e.profile_id = p.id) AS entry_count
             FROM bell_profiles p WHERE p.zone_id = ? ORDER BY p.sort_order, p.id',
            [$zoneId],
        );
    }

    /** @return array<string,mixed>|null */
    public function profile(int $id): ?array
    {
        return db()->one('SELECT * FROM bell_profiles WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function entries(int $profileId): array
    {
        return db()->all('SELECT * FROM bell_entries WHERE profile_id = ? ORDER BY ring_time, id', [$profileId]);
    }

    /** @return array<string,mixed>|null */
    public function entry(int $id): ?array
    {
        return db()->one('SELECT * FROM bell_entries WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function holidays(int $zoneId): array
    {
        return db()->all('SELECT * FROM bell_holidays WHERE zone_id = ? ORDER BY holiday_date', [$zoneId]);
    }

    /** @return array<string,mixed>|null */
    public function holiday(int $id): ?array
    {
        return db()->one('SELECT * FROM bell_holidays WHERE id = ?', [$id]);
    }

    // ------------------------------------------------------------ sounds ---

    /** @return list<array<string,mixed>> */
    public function sounds(): array
    {
        return db()->all(
            "SELECT s.*, (SELECT COUNT(*) FROM bell_entries e WHERE e.sound_ref = CONCAT('sound:', s.id)) AS use_count
             FROM bell_sounds s ORDER BY s.name",
        );
    }

    /** @return array<string,mixed>|null */
    public function sound(int $id): ?array
    {
        return db()->one('SELECT * FROM bell_sounds WHERE id = ?', [$id]);
    }

    /** @return list<int> */
    public function soundIds(): array
    {
        return array_map('intval', array_column(db()->all('SELECT id FROM bell_sounds'), 'id'));
    }

    /** @return array<string,string> sound_ref => display name */
    public function soundNames(): array
    {
        $names = [];
        foreach (BellSounds::BUILTIN as $key => $name) {
            $names["builtin:$key"] = $name;
        }
        foreach (db()->all('SELECT id, name FROM bell_sounds ORDER BY name') as $sound) {
            $names['sound:' . $sound['id']] = (string) $sound['name'];
        }
        return $names;
    }

    /** @return array<string,string> lower-case sound name => sound_ref (for CSV import) */
    public function soundRefsByName(): array
    {
        $refs = [];
        foreach ($this->soundNames() as $ref => $name) {
            $refs[mb_strtolower($name)] = $ref;
        }
        return $refs;
    }

    /** @param array<string,mixed> $meta */
    public function addSound(string $name, array $meta, ?int $userId): int
    {
        return db()->insert('bell_sounds', [
            'name' => $name, 'stored_filename' => $meta['stored_filename'], 'original_filename' => $meta['original_filename'],
            'mime_type' => $meta['mime_type'], 'size_bytes' => $meta['size_bytes'], 'sha256' => $meta['sha256'],
            'created_by' => $userId, 'created_at' => Database::now(),
        ]);
    }

    public function soundNameTaken(string $name): bool
    {
        return in_array(mb_strtolower($name), array_map('mb_strtolower', BellSounds::BUILTIN), true)
            || db()->value('SELECT COUNT(*) FROM bell_sounds WHERE name = ?', [$name]) > 0;
    }

    public function deleteSound(int $id): void
    {
        db()->run('DELETE FROM bell_sounds WHERE id = ?', [$id]);
    }

    // ----------------------------------------------------------- entries ---

    /** @param array<string,mixed> $data validated by BellData::validateEntry */
    public function saveEntry(?int $id, int $profileId, array $data): int
    {
        $profile = $this->profile($profileId) ?? throw new InvalidArgumentException('That profile does not exist.');
        $now = Database::now();
        $row = [
            'ring_time' => $data['ring_time'], 'days' => $data['days'], 'sound_ref' => $data['sound_ref'],
            'label' => $data['label'], 'enabled' => $data['enabled'], 'both_floors' => $data['both_floors'], 'updated_at' => $now,
        ];
        if ($id === null) {
            return db()->insert('bell_entries', $row + ['zone_id' => (int) $profile['zone_id'], 'profile_id' => $profileId, 'created_at' => $now]);
        }
        db()->update('bell_entries', $row, ['id' => $id]);
        return $id;
    }

    public function deleteEntry(int $id): void
    {
        db()->run('DELETE FROM bell_entries WHERE id = ?', [$id]);
    }

    public function setEntryEnabled(int $id, bool $enabled): void
    {
        db()->update('bell_entries', ['enabled' => $enabled ? 1 : 0, 'updated_at' => Database::now()], ['id' => $id]);
    }

    /**
     * Copies one row to another zone: into the profile with the same name when
     * it has one, else into that zone's active profile. Returns the profile id used.
     */
    public function copyEntry(int $entryId, int $targetZoneId): int
    {
        $entry = $this->entry($entryId) ?? throw new InvalidArgumentException('That row does not exist.');
        $source = $this->profile((int) $entry['profile_id']);
        $target = db()->one('SELECT id FROM bell_profiles WHERE zone_id = ? AND name = ?', [$targetZoneId, (string) $source['name']]);
        $profileId = $target !== null ? (int) $target['id'] : (int) ($this->zone($targetZoneId)['active_profile_id'] ?? 0);
        if ($profileId < 1) {
            throw new InvalidArgumentException('The other area has no profile to copy into.');
        }
        $this->saveEntry(null, $profileId, [
            'ring_time' => $entry['ring_time'], 'days' => $entry['days'], 'sound_ref' => $entry['sound_ref'],
            'label' => $entry['label'], 'enabled' => (int) $entry['enabled'], 'both_floors' => 0,
        ]);
        return $profileId;
    }

    /**
     * Adds rows to a profile, or replaces all its rows. Runs in one transaction,
     * so a failed import leaves the profile as it was.
     *
     * @param list<array<string,mixed>> $rows
     */
    public function importEntries(int $profileId, array $rows, bool $replace): void
    {
        db()->transaction(function () use ($profileId, $rows, $replace): void {
            if ($replace) {
                db()->run('DELETE FROM bell_entries WHERE profile_id = ?', [$profileId]);
            }
            foreach ($rows as $row) {
                $this->saveEntry(null, $profileId, $row);
            }
        });
    }

    // ---------------------------------------------------------- profiles ---

    public function setActiveProfile(int $zoneId, int $profileId): void
    {
        $profile = $this->profile($profileId);
        if ($profile === null || (int) $profile['zone_id'] !== $zoneId) {
            throw new InvalidArgumentException('That profile belongs to another area.');
        }
        db()->update('bell_zones', ['active_profile_id' => $profileId, 'updated_at' => Database::now()], ['id' => $zoneId]);
    }

    public function createProfile(int $zoneId, string $name): int
    {
        $this->checkProfileName($zoneId, $name, null);
        return db()->insert('bell_profiles', [
            'zone_id' => $zoneId, 'name' => $name,
            'sort_order' => 1 + (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) FROM bell_profiles WHERE zone_id = ?', [$zoneId]),
            'created_at' => Database::now(),
        ]);
    }

    public function renameProfile(int $id, string $name): void
    {
        $profile = $this->profile($id) ?? throw new InvalidArgumentException('That profile does not exist.');
        $this->checkProfileName((int) $profile['zone_id'], $name, $id);
        db()->update('bell_profiles', ['name' => $name], ['id' => $id]);
    }

    public function deleteProfile(int $id): void
    {
        $profile = $this->profile($id) ?? throw new InvalidArgumentException('That profile does not exist.');
        $zoneId = (int) $profile['zone_id'];
        db()->transaction(function () use ($id, $zoneId): void {
            $others = db()->all('SELECT id FROM bell_profiles WHERE zone_id = ? AND id <> ? ORDER BY sort_order, id', [$zoneId, $id]);
            if ($others === []) {
                throw new InvalidArgumentException('An area needs at least one profile.');
            }
            if ((int) ($this->zone($zoneId)['active_profile_id'] ?? 0) === $id) {
                $this->setActiveProfile($zoneId, (int) $others[0]['id']);
            }
            db()->run('DELETE FROM bell_profiles WHERE id = ?', [$id]);
        });
    }

    /** Copies a profile and all its rows into a zone (the same one or the other area). */
    public function copyProfile(int $profileId, int $targetZoneId): int
    {
        $profile = $this->profile($profileId) ?? throw new InvalidArgumentException('That profile does not exist.');
        if ($this->zone($targetZoneId) === null) {
            throw new InvalidArgumentException('That area does not exist.');
        }
        return db()->transaction(function () use ($profile, $targetZoneId): int {
            $name = (string) $profile['name'];
            $candidate = (int) $profile['zone_id'] === $targetZoneId ? $name . ' (salinan)' : $name;
            for ($n = 2; $this->profileNameExists($targetZoneId, $candidate); $n++) {
                $candidate = $name . ' (salinan ' . $n . ')';
            }
            $newId = $this->createProfile($targetZoneId, mb_substr($candidate, 0, 60));
            foreach ($this->entries((int) $profile['id']) as $entry) {
                $this->saveEntry(null, $newId, [
                    'ring_time' => $entry['ring_time'], 'days' => $entry['days'], 'sound_ref' => $entry['sound_ref'],
                    'label' => $entry['label'], 'enabled' => (int) $entry['enabled'],
                    'both_floors' => (int) $profile['zone_id'] === $targetZoneId ? (int) $entry['both_floors'] : 0,
                ]);
            }
            return $newId;
        });
    }

    private function checkProfileName(int $zoneId, string $name, ?int $exceptId): void
    {
        if ($name === '' || mb_strlen($name) > 60) {
            throw new InvalidArgumentException('Give the profile a name of 1 to 60 characters.');
        }
        if ($this->profileNameExists($zoneId, $name, $exceptId)) {
            throw new InvalidArgumentException('This area already has a profile with that name.');
        }
    }

    private function profileNameExists(int $zoneId, string $name, ?int $exceptId = null): bool
    {
        return (int) db()->value(
            'SELECT COUNT(*) FROM bell_profiles WHERE zone_id = ? AND name = ? AND id <> ?',
            [$zoneId, $name, $exceptId ?? 0],
        ) > 0;
    }

    // ------------------------------------------------------ zones, holidays ---

    public function updateZone(int $id, string $name, string $color, int $volume): void
    {
        if ($name === '' || mb_strlen($name) > 60) {
            throw new InvalidArgumentException('Give the area a name of 1 to 60 characters.');
        }
        if (!isset(BellData::COLORS[$color])) {
            throw new InvalidArgumentException('Choose one of the listed colours.');
        }
        if ($volume < 0 || $volume > 100) {
            throw new InvalidArgumentException('Volume is a number from 0 to 100.');
        }
        $taken = (int) db()->value('SELECT COUNT(*) FROM bell_zones WHERE name = ? AND id <> ?', [$name, $id]);
        if ($taken > 0) {
            throw new InvalidArgumentException('Another area already has that name.');
        }
        db()->update('bell_zones', ['name' => $name, 'color' => $color, 'volume' => $volume, 'updated_at' => Database::now()], ['id' => $id]);
    }

    public function addHoliday(int $zoneId, string $date, string $label): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid date.');
        }
        if (mb_strlen($label) > 120) {
            throw new InvalidArgumentException('Keep the holiday name to 120 characters.');
        }
        if ((int) db()->value('SELECT COUNT(*) FROM bell_holidays WHERE zone_id = ? AND holiday_date = ?', [$zoneId, $date]) > 0) {
            throw new InvalidArgumentException('That date is already a holiday on this area.');
        }
        db()->insert('bell_holidays', ['zone_id' => $zoneId, 'holiday_date' => $date, 'label' => $label]);
    }

    public function deleteHoliday(int $id): void
    {
        db()->run('DELETE FROM bell_holidays WHERE id = ?', [$id]);
    }

    // ------------------------------------------------------------ bell PC ---

    /** @return array<string,mixed> */
    public function pc(): array
    {
        return db()->one('SELECT * FROM bell_pc WHERE id = 1') ?? ['id' => 1, 'token_hash' => null, 'last_seen_at' => null];
    }

    public function isOnline(array $pc): bool
    {
        return ($pc['last_seen_at'] ?? null) !== null
            && strtotime((string) $pc['last_seen_at'] . ' UTC') >= time() - self::ONLINE_SECONDS;
    }

    /** Makes a new connection key (replacing any old one) and returns it once. */
    public function createKey(): string
    {
        $token = bin2hex(random_bytes(32));
        db()->run('UPDATE bell_pc SET token_hash = ?, token_created_at = ? WHERE id = 1', [hash('sha256', $token), Database::now()]);
        return $token;
    }

    public function revokeKey(): void
    {
        db()->run('UPDATE bell_pc SET token_hash = NULL, token_created_at = NULL WHERE id = 1');
    }

    public function keyMatches(string $token): bool
    {
        $hash = (string) ($this->pc()['token_hash'] ?? '');
        return $hash !== '' && $token !== '' && hash_equals($hash, hash('sha256', $token));
    }

    /** @param array<string,mixed> $status what the PC reports about its sound devices */
    public function recordSync(string $pcName, string $appVersion, array $status): void
    {
        db()->run(
            'UPDATE bell_pc SET last_seen_at = ?, pc_name = ?, app_version = ?, status = ? WHERE id = 1',
            [Database::now(), $pcName, $appVersion, json_encode($status, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
        );
    }

    /** @param list<array<string,mixed>> $events */
    public function addEvents(array $events): void
    {
        $now = Database::now();
        foreach ($events as $event) {
            db()->insert('bell_events', [
                'zone_id' => $event['zone_id'], 'occurred_at' => $event['occurred_at'],
                'kind' => $event['kind'], 'message' => $event['message'], 'received_at' => $now,
            ]);
        }
        // Keep the newest 2000 only: this is a diary, not an archive (the PC keeps its own files).
        db()->run('DELETE FROM bell_events WHERE id + 2000 <= (SELECT newest FROM (SELECT MAX(id) AS newest FROM bell_events) c)');
    }

    /** @return list<array<string,mixed>> */
    public function events(int $limit = 100): array
    {
        return db()->all(
            'SELECT e.*, z.name AS zone_name FROM bell_events e LEFT JOIN bell_zones z ON z.id = e.zone_id
             ORDER BY e.occurred_at DESC, e.id DESC LIMIT ' . max(1, $limit),
        );
    }

    // ---------------------------------------------------- sync and backup ---

    /**
     * What the bell PC downloads: for every zone its active profile's rows plus
     * other zones' school-wide ("both areas") rows, holidays and volume.
     *
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        $zones = $this->zones();
        $names = array_column($zones, 'name', 'id');
        $active = [];
        foreach ($zones as $zone) {
            $active[(int) $zone['id']] = (int) $zone['active_profile_id'];
        }
        $rows = $active === [] ? [] : db()->all(
            'SELECT * FROM bell_entries WHERE profile_id IN (' . implode(',', array_fill(0, count($active), '?')) . ') ORDER BY ring_time, id',
            array_values($active),
        );

        $out = [];
        foreach ($zones as $zone) {
            $zoneId = (int) $zone['id'];
            $entries = [];
            foreach ($rows as $row) {
                $own = (int) $row['zone_id'] === $zoneId;
                if (!$own && !((bool) $row['both_floors'] && (bool) $row['enabled'])) {
                    continue;
                }
                $entries[] = [
                    'id' => (int) $row['id'], 'time' => (string) $row['ring_time'], 'days' => BellData::daysList((string) $row['days']),
                    'sound' => (string) $row['sound_ref'], 'label' => (string) $row['label'], 'enabled' => (bool) $row['enabled'],
                    'shared_from' => $own ? null : (string) $names[(int) $row['zone_id']],
                ];
            }
            $profile = $this->profile($active[$zoneId]);
            $out[] = [
                'id' => $zoneId, 'name' => (string) $zone['name'], 'color' => (string) $zone['color'], 'volume' => (int) $zone['volume'],
                'profile' => $profile === null ? null : ['id' => (int) $profile['id'], 'name' => (string) $profile['name']],
                'holidays' => array_map(static fn (array $h): array => ['date' => (string) $h['holiday_date'], 'label' => (string) $h['label']], $this->holidays($zoneId)),
                'entries' => $entries,
            ];
        }

        return [
            'zones' => $out,
            'sounds' => array_map(static fn (array $s): array => [
                'id' => (int) $s['id'], 'name' => (string) $s['name'], 'sha256' => (string) $s['sha256'],
                'size' => (int) $s['size_bytes'], 'ext' => strtolower(pathinfo((string) $s['stored_filename'], PATHINFO_EXTENSION)),
            ], $this->sounds()),
            'builtin' => array_map(static fn (string $key, string $name): array => ['key' => $key, 'name' => $name], array_keys(BellSounds::BUILTIN), BellSounds::BUILTIN),
        ];
    }

    /** A short fingerprint of the payload; the PC sends it back to skip unchanged downloads. */
    public static function version(array $payload): string
    {
        return substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * Everything the owner configured, as one readable file (sound files are
     * not inside; their names are, so rows can be matched again).
     *
     * @return array<string,mixed>
     */
    public function backup(): array
    {
        $names = $this->soundNames();
        $zones = [];
        foreach ($this->zones() as $zone) {
            $profiles = [];
            foreach ($this->profiles((int) $zone['id']) as $profile) {
                $profiles[] = [
                    'name' => (string) $profile['name'],
                    'active' => (int) $profile['id'] === (int) $zone['active_profile_id'],
                    'entries' => array_map(static fn (array $e): array => [
                        'time' => (string) $e['ring_time'], 'days' => BellData::daysList((string) $e['days']),
                        'sound' => $names[(string) $e['sound_ref']] ?? (string) $e['sound_ref'],
                        'label' => (string) $e['label'], 'enabled' => (bool) $e['enabled'], 'both_floors' => (bool) $e['both_floors'],
                    ], $this->entries((int) $profile['id'])),
                ];
            }
            $zones[] = [
                'name' => (string) $zone['name'], 'color' => (string) $zone['color'], 'volume' => (int) $zone['volume'],
                'profiles' => $profiles,
                'holidays' => array_map(static fn (array $h): array => ['date' => (string) $h['holiday_date'], 'label' => (string) $h['label']], $this->holidays((int) $zone['id'])),
            ];
        }
        return ['app' => 'nexus-bell', 'version' => 1, 'exported_at' => gmdate('c'), 'zones' => $zones, 'sounds' => array_values($names)];
    }
}
