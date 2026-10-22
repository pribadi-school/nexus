<?php

declare(strict_types=1);

namespace App\Modules\Bell;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * The API the bell PC uses. It is public (no sign-in) but needs the connection
 * key made on the Bell PC page. The PC keeps its own copy of the schedule, so
 * these calls only update that copy; they are never needed to ring a bell.
 */
final class BellApiController
{
    private const KINDS = ['played', 'skipped', 'failed', 'warning', 'info'];

    private BellRepository $bell;

    public function __construct()
    {
        $this->bell = new BellRepository();
    }

    /**
     * POST /bell/api/sync  (version, pc_name, app_version, status, events)
     * Checks in, stores what the PC reports, and returns the schedule when it
     * has changed since the version the PC already has.
     */
    public function sync(Request $request): Response
    {
        $this->authorize($request);
        $name = $this->clean((string) $request->input('pc_name', ''), 80);
        $this->bell->recordSync($name, $this->clean((string) $request->input('app_version', ''), 20), $this->status((string) ($request->body['status'] ?? '')));
        $events = $this->events((string) ($request->body['events'] ?? ''));
        if ($events !== []) {
            $this->bell->addEvents($events);
        }

        $payload = $this->bell->payload();
        $version = BellRepository::version($payload);
        $changed = (string) $request->input('version', '') !== $version;
        $this->release();
        return Response::json(['ok' => true, 'version' => $version, 'changed' => $changed, 'events_saved' => count($events)] + ($changed ? ['data' => $payload] : []));
    }

    /** GET /bell/api/sounds/{id} : one uploaded sound file. */
    public function sound(Request $request): Response
    {
        $this->authorize($request);
        $sound = $this->bell->sound((int) $request->param('id')) ?? throw HttpException::notFound('That sound does not exist.');
        $path = BellSounds::path((string) $sound['stored_filename']);
        if (!is_file($path)) {
            throw HttpException::notFound('The sound file is missing on the server.');
        }
        $this->release();
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => BellSounds::mime((string) $sound['stored_filename']),
            'X-Content-Type-Options' => 'nosniff',
            'X-Sound-Sha256' => (string) $sound['sha256'],
        ]);
    }

    private function authorize(Request $request): void
    {
        // A custom header: some cPanel PHP setups drop Authorization.
        $sent = (string) $request->header('X-Nexus-Bell-Token');
        if (!$this->bell->keyMatches($sent)) {
            throw new HttpException(403, 'This bell PC is not connected. Make a connection key on the Bell PC page.');
        }
    }

    /** @return array<string,mixed> */
    private function status(string $json): array
    {
        $data = json_decode($json, true);
        return is_array($data) ? array_slice($data, 0, 20, true) : [];
    }

    /** @return list<array<string,mixed>> */
    private function events(string $json): array
    {
        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            return [];
        }
        $zones = array_map('intval', array_column($this->bell->zones(), 'id'));
        $out = [];
        foreach (array_slice($rows, 0, 200) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $when = (string) ($row['at'] ?? '');
            $kind = (string) ($row['kind'] ?? '');
            if (!preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $when) || !in_array($kind, self::KINDS, true)) {
                continue;
            }
            $zone = isset($row['zone']) && in_array((int) $row['zone'], $zones, true) ? (int) $row['zone'] : null;
            $out[] = ['zone_id' => $zone, 'occurred_at' => $when, 'kind' => $kind, 'message' => $this->clean((string) ($row['msg'] ?? ''), 300)];
        }
        return $out;
    }

    private function clean(string $text, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/', ' ', $text) ?? ''), 0, $max);
    }

    private function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
