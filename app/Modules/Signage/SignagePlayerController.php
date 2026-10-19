<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/** The unguessable screen token grants read access to its current playlist only. */
final class SignagePlayerController
{
    private SignageRepository $repository;

    public function __construct()
    {
        $this->repository = new SignageRepository();
    }

    public function player(Request $request): Response
    {
        $screen = $this->screenForToken($request);
        $this->releaseSession();
        return Response::playerHtml(View::render('Signage::player', [
            'name' => $screen['name'],
            'manifestUrl' => url('/signage/player/' . $screen['token'] . '/manifest'),
            'preview' => false,
        ], null));
    }

    public function manifest(Request $request): Response
    {
        $screen = $this->screenForToken($request);
        $this->releaseSession();
        $playlist = $screen['playlist_id'] === null ? null : $this->repository->playlist((int) $screen['playlist_id']);
        $items = $playlist !== null && (bool) $screen['enabled'] ? $this->repository->playlistItems((int) $playlist['id']) : [];
        if ($request->method !== 'HEAD') {
            $this->heartbeat($request, $screen, $items);
        }
        $prefix = '/signage/player/' . $screen['token'] . '/media/';
        return Response::json($this->manifestData($screen, $playlist, $items, $prefix));
    }

    public function media(Request $request): Response
    {
        $screen = $this->screenForToken($request);
        $this->releaseSession();
        if (!(bool) $screen['enabled'] || $screen['playlist_id'] === null) {
            throw HttpException::notFound();
        }
        $contentId = (int) $request->param('id');
        foreach ($this->repository->playlistItems((int) $screen['playlist_id']) as $item) {
            if ((int) $item['id'] === $contentId && in_array($item['type'], ['video', 'image'], true)) {
                return $this->videoResponse($request, $item);
            }
        }
        throw HttpException::notFound();
    }

    public function preview(Request $request): Response
    {
        $playlist = $this->repository->playlist((int) $request->param('id'));
        if ($playlist === null) {
            throw HttpException::notFound();
        }
        $this->releaseSession();
        return Response::playerHtml(View::render('Signage::player', [
            'name' => $playlist['name'] . ' · Preview',
            'manifestUrl' => url('/signage/playlists/' . $playlist['id'] . '/preview/manifest'),
            'preview' => true,
        ], null));
    }

    public function previewManifest(Request $request): Response
    {
        $playlist = $this->repository->playlist((int) $request->param('id'));
        if ($playlist === null) {
            throw HttpException::notFound();
        }
        $this->releaseSession();
        return Response::json($this->manifestData([
            'name' => $playlist['name'] . ' · Preview',
            'location' => '',
            'orientation' => 'landscape',
            'fit' => 'contain',
            'muted' => true,
            'enabled' => true,
        ], $playlist, $this->repository->playlistItems((int) $playlist['id']), '/signage/preview/media/'));
    }

    public function previewMedia(Request $request): Response
    {
        $this->releaseSession();
        $item = db()->one("SELECT * FROM signage_content WHERE id = ? AND type IN ('video', 'image')", [(int) $request->param('id')]);
        if ($item === null) {
            throw HttpException::notFound();
        }
        return $this->videoResponse($request, $item);
    }

    /** @return array<string,mixed> */
    private function screenForToken(Request $request): array
    {
        $token = $request->param('token');
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            throw HttpException::notFound();
        }
        $screen = $this->repository->screenByToken($token);
        if ($screen === null) {
            throw HttpException::notFound();
        }
        return $screen;
    }

    /** @param list<array<string,mixed>> $items */
    private function heartbeat(Request $request, array $screen, array $items): void
    {
        $current = null;
        $itemId = $request->query('item');
        if ($itemId !== null && preg_match('/\A[1-9][0-9]{0,9}\z/', $itemId)) {
            foreach ($items as $item) {
                if ((int) $item['item_id'] === (int) $itemId) {
                    $current = (int) $item['id'];
                    break;
                }
            }
        }
        $error = $request->query('error');
        if ($error !== null) {
            $error = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', ' ', $error) ?? '', 0, 500);
        }
        db()->update('signage_screens', [
            'last_seen_at' => Database::now(),
            'last_content_id' => $current,
            'last_error' => $error === '' ? null : $error,
        ], ['id' => (int) $screen['id'], 'token' => $screen['token']]);
    }

    /** @param list<array<string,mixed>> $items @return array<string,mixed> */
    private function manifestData(array $screen, ?array $playlist, array $items, string $mediaPrefix): array
    {
        $data = [
            'screen' => [
                'name' => $screen['name'],
                'location' => $screen['location'] ?? '',
                'orientation' => $screen['orientation'] === 'portrait' ? 'portrait' : 'landscape',
                'fit' => $screen['fit'] === 'cover' ? 'cover' : 'contain',
                'muted' => (bool) $screen['muted'],
                'enabled' => (bool) $screen['enabled'],
            ],
            'playlist' => $playlist === null ? null : ['id' => (int) $playlist['id'], 'name' => $playlist['name']],
            'items' => [],
            'poll_seconds' => max(10, min(120, (int) config('signage.poll_seconds', 30))),
        ];
        foreach ($items as $item) {
            if (!in_array($item['type'], ['video', 'image', 'website'], true)) {
                continue;
            }
            if ($item['type'] === 'website' && !preg_match('#\Ahttps?://#i', (string) $item['source_url'])) {
                continue;
            }
            $data['items'][] = [
                'id' => (int) $item['item_id'],
                'content_id' => (int) $item['id'],
                'name' => $item['name'],
                'type' => $item['type'],
                'size_bytes' => $item['type'] !== 'website' ? (int) $item['size_bytes'] : 0,
                'mime_type' => $item['mime_type'],
                'url' => $item['type'] !== 'website' ? url($mediaPrefix . $item['id'], [
                    'v' => substr(hash('sha256', (string) $item['stored_filename']), 0, 16),
                ]) : $item['source_url'],
                'duration_seconds' => max(5, min(86400, (int) ($item['duration_seconds'] ?? 30))),
            ];
        }
        $data['revision'] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        return $data;
    }

    private function videoResponse(Request $request, array $item): Response
    {
        $path = SignageStorage::path((string) $item['stored_filename']);
        if (!is_file($path) || !is_readable($path)) {
            throw HttpException::notFound();
        }
        $size = filesize($path);
        if ($size === false || $size < 1) {
            throw HttpException::notFound();
        }
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $headers = [
            'Content-Type' => match (pathinfo((string) $item['stored_filename'], PATHINFO_EXTENSION)) {
                'mov' => 'video/quicktime', 'png' => 'image/png', 'jpg' => 'image/jpeg', default => 'video/mp4',
            },
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="media.' . pathinfo((string) $item['stored_filename'], PATHINFO_EXTENSION) . '"',
        ];
        $range = $request->header('Range');
        if ($range !== null) {
            if (strlen($range) > 100 || !preg_match('/\Abytes=([0-9]*)-([0-9]*)\z/', trim($range), $matches)
                || ($matches[1] === '' && $matches[2] === '')) {
                return Response::stream(static function (): void {}, 416, $headers + [
                    'Content-Range' => 'bytes */' . $size, 'Content-Length' => '0',
                ]);
            }
            if ($matches[1] === '') {
                $suffix = (int) $matches[2];
                $start = max(0, $size - $suffix);
                if ($suffix < 1) {
                    $start = $size;
                }
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] === '' ? $end : min($end, (int) $matches[2]);
            }
            if ($start >= $size || $end < $start) {
                return Response::stream(static function (): void {}, 416, $headers + [
                    'Content-Range' => 'bytes */' . $size, 'Content-Length' => '0',
                ]);
            }
            $status = 206;
            $headers['Content-Range'] = "bytes $start-$end/$size";
        }
        $length = $end - $start + 1;
        $headers['Content-Length'] = (string) $length;
        return Response::stream(static function () use ($path, $start, $length): void {
            // Avoid PHP output buffering turning a large video into one allocation.
            while (ob_get_level() > 0) {
                if (!@ob_end_clean()) {
                    break;
                }
            }
            @ini_set('zlib.output_compression', '0');
            @set_time_limit(0);
            $handle = fopen($path, 'rb');
            if ($handle === false) {
                return;
            }
            try {
                if (fseek($handle, $start) !== 0) {
                    return;
                }
                $remaining = $length;
                while ($remaining > 0 && !feof($handle) && !connection_aborted()) {
                    $chunk = fread($handle, min(262144, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                    flush();
                }
            } finally {
                fclose($handle);
            }
        }, $status, $headers);
    }

    private function releaseSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
