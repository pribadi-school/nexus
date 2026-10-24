<?php

declare(strict_types=1);

namespace App\Modules\Bell;

use App\Auth\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use InvalidArgumentException;
use RuntimeException;

/** The Bell pages: each area's schedule, its settings, the sound library and the bell PC. */
final class BellController
{
    private BellRepository $bell;

    public function __construct()
    {
        $this->bell = new BellRepository();
    }

    /** GET /bell : opens the first area. */
    public function index(Request $request): Response
    {
        $zones = $this->bell->zones();
        return $zones === [] ? $this->render('empty', [], 'Bell') : Response::redirect('/bell/zones/' . $zones[0]['id']);
    }

    /** GET /bell/zones/{id}?profile= : one area's schedule. */
    public function zone(Request $request): Response
    {
        $zone = $this->zoneOf($request);
        $profiles = $this->bell->profiles((int) $zone['id']);
        $selected = null;
        $wanted = (int) $request->query('profile', '0');
        foreach ($profiles as $profile) {
            if ((int) $profile['id'] === $wanted || ($selected === null && (int) $profile['id'] === (int) $zone['active_profile_id'])) {
                $selected = $profile;
            }
        }
        $selected ??= $profiles[0] ?? null;
        $others = array_values(array_filter($this->bell->zones(), static fn (array $z): bool => $z['id'] !== $zone['id']));
        return $this->render('zone', [
            'zone' => $zone, 'profiles' => $profiles, 'selected' => $selected, 'others' => $others,
            'entries' => $selected === null ? [] : $this->bell->entries((int) $selected['id']),
            'soundNames' => $this->bell->soundNames(), 'tab' => 'zone-' . $zone['id'],
        ], (string) $zone['name']);
    }

    /** POST /bell/zones/{id}/active-profile */
    public function activate(Request $request): Response
    {
        $zone = $this->zoneOf($request);
        $profileId = (int) $request->input('profile_id', '0');
        return $this->attempt(function () use ($zone, $profileId): string {
            $this->bell->setActiveProfile((int) $zone['id'], $profileId);
            return $zone['name'] . ' now rings with the "' . $this->bell->profile($profileId)['name'] . '" profile. The bell PC picks this up within about 30 seconds.';
        }, '/bell/zones/' . $zone['id'] . '?profile=' . $profileId);
    }

    /** GET /bell/zones/{id}/settings */
    public function settings(Request $request): Response
    {
        return $this->settingsPage($this->zoneOf($request));
    }

    /** POST /bell/zones/{id}/settings */
    public function saveSettings(Request $request): Response
    {
        $zone = $this->zoneOf($request);
        return $this->attempt(function () use ($zone, $request): string {
            $this->bell->updateZone((int) $zone['id'], trim((string) $request->input('name', '')), (string) $request->input('color', ''), (int) $request->input('volume', '100'));
            return 'Area settings saved.';
        }, '/bell/zones/' . $zone['id'] . '/settings');
    }

    /** POST /bell/zones/{id}/profiles */
    public function createProfile(Request $request): Response
    {
        $zone = $this->zoneOf($request);
        return $this->attempt(function () use ($zone, $request): string {
            $this->bell->createProfile((int) $zone['id'], trim((string) $request->input('name', '')));
            return 'Profile added.';
        }, '/bell/zones/' . $zone['id'] . '/settings#profiles');
    }

    /** POST /bell/profiles/{id} : rename */
    public function renameProfile(Request $request): Response
    {
        $profile = $this->profileOf($request);
        return $this->attempt(function () use ($profile, $request): string {
            $this->bell->renameProfile((int) $profile['id'], trim((string) $request->input('name', '')));
            return 'Profile renamed.';
        }, '/bell/zones/' . $profile['zone_id'] . '/settings#profiles');
    }

    /** POST /bell/profiles/{id}/delete */
    public function deleteProfile(Request $request): Response
    {
        $profile = $this->profileOf($request);
        return $this->attempt(function () use ($profile): string {
            $this->bell->deleteProfile((int) $profile['id']);
            return 'Profile "' . $profile['name'] . '" deleted.';
        }, '/bell/zones/' . $profile['zone_id'] . '/settings#profiles');
    }

    /** POST /bell/profiles/{id}/copy (zone_id) : copy a whole profile to an area */
    public function copyProfile(Request $request): Response
    {
        $profile = $this->profileOf($request);
        $target = (int) $request->input('zone_id', '0');
        return $this->attempt(function () use ($profile, $target): string {
            $this->bell->copyProfile((int) $profile['id'], $target);
            return 'Profile copied to ' . ($this->bell->zone($target)['name'] ?? 'the area') . '.';
        }, '/bell/zones/' . $profile['zone_id'] . '/settings#profiles');
    }

    // ------------------------------------------------------------ entries ---

    /** GET /bell/profiles/{id}/entries/create */
    public function createEntry(Request $request): Response
    {
        $profile = $this->profileOf($request);
        return $this->entryForm($profile, null, [
            'ring_time' => '07:00:00', 'days' => [1, 2, 3, 4, 5], 'sound_ref' => 'builtin:bel-sekolah', 'label' => '', 'enabled' => 1, 'both_floors' => 0,
        ]);
    }

    /** POST /bell/profiles/{id}/entries */
    public function storeEntry(Request $request): Response
    {
        return $this->saveEntry($this->profileOf($request), null, $request);
    }

    /** GET /bell/entries/{id}/edit */
    public function editEntry(Request $request): Response
    {
        $entry = $this->entryOf($request);
        $profile = $this->bell->profile((int) $entry['profile_id']);
        return $this->entryForm($profile, $entry, [
            'ring_time' => $entry['ring_time'], 'days' => BellData::daysList((string) $entry['days']), 'sound_ref' => $entry['sound_ref'],
            'label' => $entry['label'], 'enabled' => (int) $entry['enabled'], 'both_floors' => (int) $entry['both_floors'],
        ]);
    }

    /** POST /bell/entries/{id} */
    public function updateEntry(Request $request): Response
    {
        $entry = $this->entryOf($request);
        return $this->saveEntry($this->bell->profile((int) $entry['profile_id']), $entry, $request);
    }

    /** POST /bell/entries/{id}/toggle */
    public function toggleEntry(Request $request): Response
    {
        $entry = $this->entryOf($request);
        $this->bell->setEntryEnabled((int) $entry['id'], !(bool) $entry['enabled']);
        return Response::redirect($this->zoneUrl($entry));
    }

    /** POST /bell/entries/{id}/delete */
    public function deleteEntry(Request $request): Response
    {
        $entry = $this->entryOf($request);
        $this->bell->deleteEntry((int) $entry['id']);
        Session::flash('success', 'Bell removed from the schedule.');
        return Response::redirect($this->zoneUrl($entry));
    }

    /** POST /bell/entries/{id}/copy (zone_id) */
    public function copyEntry(Request $request): Response
    {
        $entry = $this->entryOf($request);
        $target = (int) $request->input('zone_id', '0');
        return $this->attempt(function () use ($entry, $target): string {
            $this->bell->copyEntry((int) $entry['id'], $target);
            return 'Bell copied to ' . ($this->bell->zone($target)['name'] ?? 'the other area') . '.';
        }, $this->zoneUrl($entry));
    }

    /** GET /bell/profiles/{id}/export.csv */
    public function exportCsv(Request $request): Response
    {
        $profile = $this->profileOf($request);
        $zone = $this->bell->zone((int) $profile['zone_id']);
        $csv = BellData::toCsv($this->bell->entries((int) $profile['id']), $this->bell->soundNames());
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $zone['name'] . '-' . $profile['name']) . '.csv';
        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $name . '"']);
    }

    /** POST /bell/profiles/{id}/import (file, replace) */
    public function importCsv(Request $request): Response
    {
        $profile = $this->profileOf($request);
        $back = '/bell/zones/' . $profile['zone_id'] . '?profile=' . $profile['id'];
        $file = $request->files['file'] ?? [];
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            Session::flash('error', 'Choose a CSV file to import.');
            return Response::redirect($back);
        }
        if ((int) ($file['size'] ?? 0) > 1024 * 1024) {
            Session::flash('error', 'That file is too large for a bell schedule (1 MB at most).');
            return Response::redirect($back);
        }
        [$rows, $errors] = BellData::fromCsv((string) file_get_contents($file['tmp_name']), $this->bell->soundRefsByName());
        if ($errors !== []) {
            // All or nothing, so a typo never leaves a half-imported schedule.
            Session::flash('error', 'Nothing was imported. ' . implode(' ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' (and ' . (count($errors) - 5) . ' more)' : ''));
            return Response::redirect($back);
        }
        if ($rows === []) {
            Session::flash('error', 'That file has no bell rows.');
            return Response::redirect($back);
        }
        $this->bell->importEntries((int) $profile['id'], $rows, $request->input('replace') === '1');
        Session::flash('success', plural(count($rows), 'bell') . ' imported.');
        return Response::redirect($back);
    }

    /** GET /bell/backup.json */
    public function backup(Request $request): Response
    {
        $json = json_encode($this->bell->backup(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="nexus-bell-backup-' . gmdate('Y-m-d') . '.json"',
        ]);
    }

    // ----------------------------------------------------------- holidays ---

    /** POST /bell/zones/{id}/holidays */
    public function addHoliday(Request $request): Response
    {
        $zone = $this->zoneOf($request);
        return $this->attempt(function () use ($zone, $request): string {
            $this->bell->addHoliday((int) $zone['id'], (string) $request->input('date', ''), trim((string) $request->input('label', '')));
            return 'Holiday added. No bells ring on this area that day.';
        }, '/bell/zones/' . $zone['id'] . '/settings#holidays');
    }

    /** POST /bell/holidays/{id}/delete */
    public function deleteHoliday(Request $request): Response
    {
        $holiday = $this->bell->holiday((int) $request->param('id')) ?? throw HttpException::notFound('That holiday does not exist.');
        $this->bell->deleteHoliday((int) $holiday['id']);
        Session::flash('success', 'Holiday removed.');
        return Response::redirect('/bell/zones/' . $holiday['zone_id'] . '/settings#holidays');
    }

    // ------------------------------------------------------------- sounds ---

    /** GET /bell/sounds */
    public function sounds(Request $request): Response
    {
        return $this->render('sounds', ['sounds' => $this->bell->sounds(), 'tab' => 'sounds', 'values' => []], 'Sounds');
    }

    /** POST /bell/sounds (name, file) */
    public function uploadSound(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $file = $request->files['file'] ?? [];
        $stored = null;
        try {
            if ($name === '' || mb_strlen($name) > 80) {
                throw new RuntimeException('Give the sound a name of 1 to 80 characters.');
            }
            if ($this->bell->soundNameTaken($name)) {
                throw new RuntimeException('A sound with that name already exists.');
            }
            $stored = BellSounds::store(is_array($file) ? $file : []);
            $this->bell->addSound($name, $stored, Auth::id());
        } catch (RuntimeException $error) {
            if ($stored !== null) {
                BellSounds::delete($stored['stored_filename']);
            }
            Session::flash('error', $error->getMessage());
            return Response::redirect('/bell/sounds');
        }
        Session::flash('success', 'Sound "' . $name . '" added. The bell PC downloads it on its next check.');
        return Response::redirect('/bell/sounds');
    }

    /** POST /bell/sounds/{id}/delete */
    public function deleteSound(Request $request): Response
    {
        $sound = $this->bell->sound((int) $request->param('id')) ?? throw HttpException::notFound('That sound does not exist.');
        $uses = (int) db()->value("SELECT COUNT(*) FROM bell_entries WHERE sound_ref = ?", ['sound:' . $sound['id']]);
        if ($uses > 0) {
            Session::flash('error', '"' . $sound['name'] . '" is used by ' . plural($uses, 'bell') . '. Change those bells first, then delete the sound.');
            return Response::redirect('/bell/sounds');
        }
        $this->bell->deleteSound((int) $sound['id']);
        BellSounds::delete((string) $sound['stored_filename']);
        Session::flash('success', 'Sound deleted.');
        return Response::redirect('/bell/sounds');
    }

    /** GET /bell/sounds/{id}/file : preview in the browser */
    public function soundFile(Request $request): Response
    {
        $sound = $this->bell->sound((int) $request->param('id')) ?? throw HttpException::notFound('That sound does not exist.');
        return $this->file(BellSounds::path((string) $sound['stored_filename']), BellSounds::mime((string) $sound['stored_filename']));
    }

    /** GET /bell/sounds/builtin/{key} */
    public function builtinFile(Request $request): Response
    {
        $key = $request->param('key');
        if (!isset(BellSounds::BUILTIN[$key])) {
            throw HttpException::notFound('That sound does not exist.');
        }
        return $this->file(BellSounds::builtinPath($key), 'audio/wav');
    }

    // ------------------------------------------------------------- bell PC ---

    /** GET /bell/pc */
    public function pc(Request $request): Response
    {
        $pc = $this->bell->pc();
        $status = is_string($pc['status'] ?? null) ? (json_decode($pc['status'], true) ?: []) : [];
        return $this->render('pc', [
            'pc' => $pc, 'status' => $status, 'online' => $this->bell->isOnline($pc), 'events' => $this->bell->events(60),
            'serverUrl' => rtrim((string) config('app.url', ''), '/'), 'tab' => 'pc', 'zones' => $this->bell->zones(),
        ], 'Bell PC');
    }

    /** POST /bell/pc/key : make (or replace) the connection key, shown once */
    public function createKey(Request $request): Response
    {
        $key = $this->bell->createKey();
        Session::flash('success', 'New connection key: ' . $key . ' (copy it now; it is shown only once and any older key stops working).');
        return Response::redirect('/bell/pc');
    }

    /** POST /bell/pc/key/revoke */
    public function revokeKey(Request $request): Response
    {
        $this->bell->revokeKey();
        Session::flash('success', 'Connection key removed. The bell PC can no longer download changes, but it keeps ringing from its last copy.');
        return Response::redirect('/bell/pc');
    }

    // ------------------------------------------------------------ helpers ---

    /** @param array<string,mixed> $profile @param array<string,mixed>|null $entry @param array<string,mixed> $values @param array<string,string> $errors */
    private function entryForm(array $profile, ?array $entry, array $values, array $errors = []): Response
    {
        $zone = $this->bell->zone((int) $profile['zone_id']);
        return $this->render('entry-form', [
            'zone' => $zone, 'profile' => $profile, 'entry' => $entry, 'values' => $values, 'errors' => $errors,
            'soundNames' => $this->bell->soundNames(), 'tab' => 'zone-' . $zone['id'],
        ], $entry === null ? 'Add bell' : 'Edit bell', $errors === [] ? 200 : 422);
    }

    /** @param array<string,mixed> $profile @param array<string,mixed>|null $entry */
    private function saveEntry(array $profile, ?array $entry, Request $request): Response
    {
        [$data, $errors] = BellData::validateEntry($request->body, $this->bell->soundIds());
        if ($errors !== []) {
            return $this->entryForm($profile, $entry, [
                'ring_time' => (string) ($request->body['ring_time'] ?? ''), 'days' => array_map('intval', (array) ($request->body['days'] ?? [])),
                'sound_ref' => $data['sound_ref'], 'label' => $data['label'], 'enabled' => $data['enabled'], 'both_floors' => $data['both_floors'],
            ], $errors);
        }
        $this->bell->saveEntry($entry === null ? null : (int) $entry['id'], (int) $profile['id'], $data);
        Session::flash('success', $entry === null ? 'Bell added.' : 'Bell saved.');
        return Response::redirect('/bell/zones/' . $profile['zone_id'] . '?profile=' . $profile['id']);
    }

    /** @param array<string,mixed> $zone */
    private function settingsPage(array $zone): Response
    {
        return $this->render('zone-settings', [
            'zone' => $zone, 'profiles' => $this->bell->profiles((int) $zone['id']), 'holidays' => $this->bell->holidays((int) $zone['id']),
            'others' => array_values(array_filter($this->bell->zones(), static fn (array $z): bool => $z['id'] !== $zone['id'])),
            'tab' => 'zone-' . $zone['id'],
        ], $zone['name'] . ' settings');
    }

    /** Runs a change; shows its message, or the validation error, then goes back. */
    private function attempt(callable $change, string $back): Response
    {
        try {
            Session::flash('success', $change());
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
        }
        return Response::redirect($back);
    }

    /** @param array<string,mixed> $entry */
    private function zoneUrl(array $entry): string
    {
        return '/bell/zones/' . $entry['zone_id'] . '?profile=' . $entry['profile_id'];
    }

    private function file(string $path, string $mime): Response
    {
        if (!is_file($path)) {
            throw HttpException::notFound('The sound file is missing on the server.');
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        return new Response((string) file_get_contents($path), 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @param array<string,mixed> $data */
    private function render(string $view, array $data, string $title, int $status = 200): Response
    {
        return Response::html(View::render('Bell::' . $view, $data + ['title' => $title, 'tab' => 'zone-0'], 'layouts/app', $title), $status);
    }

    /** @return array<string,mixed> */
    private function zoneOf(Request $request): array
    {
        return $this->bell->zone((int) $request->param('id')) ?? throw HttpException::notFound('That area does not exist.');
    }

    /** @return array<string,mixed> */
    private function profileOf(Request $request): array
    {
        return $this->bell->profile((int) $request->param('id')) ?? throw HttpException::notFound('That profile does not exist.');
    }

    /** @return array<string,mixed> */
    private function entryOf(Request $request): array
    {
        return $this->bell->entry((int) $request->param('id')) ?? throw HttpException::notFound('That bell does not exist.');
    }
}
