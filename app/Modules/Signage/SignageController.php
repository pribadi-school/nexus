<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SignageController
{
    private SignageRepository $signage;

    public function __construct()
    {
        $this->signage = new SignageRepository();
    }

    public function index(Request $request): Response
    {
        return $this->render('screens', ['screens' => $this->signage->screens()], 'screens', 'Signage');
    }

    public function createScreen(Request $request): Response
    {
        return $this->screenForm(null, [
            'name' => null, 'playlist_id' => null,
            'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1,
        ]);
    }

    public function storeScreen(Request $request): Response
    {
        [$data, $errors] = SignageValidation::screen($request->body);
        if ($errors !== []) {
            return $this->screenForm(null, $data, $errors);
        }
        $id = $this->signage->createScreenWithPlaylist($data);
        Session::flash('success', 'Screen added. Add videos, images or websites to its playlist below.');
        return Response::redirect('/signage/screens/' . $id . '/edit');
    }

    public function editScreen(Request $request): Response
    {
        $screen = $this->screen($request);
        return $this->screenForm($screen, $screen, [], 'playlist');
    }

    /** GET /signage/screens/{id}/settings : name, display options, photo and player address. */
    public function screenSettings(Request $request): Response
    {
        $screen = $this->screen($request);
        return $this->screenForm($screen, $screen, [], 'settings');
    }

    /** GET /signage/screens/{id}/control : TV control buttons (when the helper is set up). */
    public function screenControl(Request $request): Response
    {
        $screen = $this->screen($request);
        if (!SignageTvControl::enabled()) {
            return Response::redirect('/signage/screens/' . $screen['id'] . '/edit');
        }
        return $this->screenForm($screen, $screen, [], 'control');
    }

    public function updateScreen(Request $request): Response
    {
        $screen = $this->screen($request);
        [$data, $errors] = SignageValidation::screen($request->body);
        if ($errors !== []) {
            return $this->screenForm($screen, $data, $errors, 'settings');
        }
        $this->signage->withScreenPlaylist((int) $screen['id'], function (int $playlistId) use ($screen, $data): void {
            $data['playlist_id'] = $playlistId;
            $this->signage->updateScreen((int) $screen['id'], $data);
        });
        Session::flash('success', 'Screen settings saved.');
        return Response::redirect('/signage/screens/' . $screen['id'] . '/settings');
    }

    /** GET /signage/screens/{id}/photo : the TV photo (signed-in users only). */
    public function screenPhoto(Request $request): Response
    {
        $screen = $this->screen($request);
        $filename = (string) ($screen['photo_filename'] ?? '');
        $path = $filename !== '' ? SignageScreenPhoto::path($filename) : '';
        if ($path === '' || !is_file($path)) {
            throw HttpException::notFound('This screen has no photo.');
        }
        // Photo downloads do not need to hold up navigation in this session.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        // The URL carries the file name as its version, so it can be cached.
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => SignageScreenPhoto::mime($filename),
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    /** POST /signage/screens/{id}/photo : upload or replace the TV photo. */
    public function uploadScreenPhoto(Request $request): Response
    {
        $screen = $this->screen($request);
        try {
            $filename = SignageScreenPhoto::store($request->files['photo'] ?? []);
        } catch (RuntimeException $error) {
            Session::flash('error', $error->getMessage());
            return Response::redirect('/signage/screens/' . $screen['id'] . '/settings#screen-photo');
        }
        SignageScreenPhoto::delete($this->signage->setScreenPhoto((int) $screen['id'], $filename));
        Session::flash('success', 'Screen photo saved.');
        return Response::redirect('/signage/screens/' . $screen['id'] . '/settings#screen-photo');
    }

    /** POST /signage/screens/{id}/photo/delete */
    public function deleteScreenPhoto(Request $request): Response
    {
        $screen = $this->screen($request);
        SignageScreenPhoto::delete($this->signage->setScreenPhoto((int) $screen['id'], null));
        Session::flash('success', 'Screen photo removed.');
        return Response::redirect('/signage/screens/' . $screen['id'] . '/settings#screen-photo');
    }

    public function deleteScreen(Request $request): Response
    {
        $screen = $this->screen($request);
        $this->signage->deleteScreen((int) $screen['id']);
        SignageScreenPhoto::delete($screen['photo_filename'] ?? null);
        Session::flash('success', 'Screen deleted. Its player address has been disabled.');
        return Response::redirect('/signage/screens');
    }

    public function rotateScreenToken(Request $request): Response
    {
        $screen = $this->screen($request);
        $this->signage->rotateScreenToken((int) $screen['id']);
        Session::flash('success', 'Player address replaced. Open the new address on the TV.');
        return Response::redirect('/signage/screens/' . $screen['id'] . '/settings');
    }

    public function content(Request $request): Response
    {
        return $this->render('content', [
            'content' => $this->signage->content(), 'usageBytes' => $this->signage->usageBytes(),
            'screens' => $this->signage->screens(),
            'screenMemberships' => $this->signage->contentScreens(),
            'uploadLimitBytes' => SignageStorage::maxUploadBytes(),
        ], 'content', 'Signage content');
    }

    public function createContent(Request $request): Response
    {
        return $this->contentForm(null, ['name' => null, 'type' => 'video', 'source_url' => null]);
    }

    public function storeContent(Request $request): Response
    {
        [$data, $errors] = SignageValidation::content($this->contentInput($request));
        if ($errors !== []) {
            return $request->isFetch()
                ? Response::json(['error' => 'Check the highlighted fields.', 'errors' => $errors], 422)
                : $this->contentForm(null, $data, $errors);
        }
        $uploaded = null;
        if (in_array($data['type'], ['video', 'image'], true)) {
            try {
                $uploaded = SignageStorage::storeUpload(is_array($request->files['file'] ?? null) ? $request->files['file'] : [], $data['type']);
            } catch (RuntimeException $error) {
                return $request->isFetch()
                    ? Response::json(['error' => $error->getMessage(), 'errors' => ['file' => $error->getMessage()]], 422)
                    : $this->contentForm(null, $data, ['file' => $error->getMessage()]);
            }
        }
        try {
            $id = $this->signage->createContent($data + ($uploaded ?? []));
        } catch (Throwable $error) {
            if ($uploaded !== null) {
                SignageStorage::delete($uploaded['stored_filename']);
            }
            throw $error;
        }
        if ($request->isFetch()) {
            $entry = $this->signage->contentItem($id);
            $screenId = filter_var($request->input('screen_id', '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            return Response::json([
                'id' => $id, 'name' => $entry['name'], 'size_bytes' => (int) $entry['size_bytes'],
                'card_html' => partial('Signage::_content-card', ['entry' => $entry, 'screens' => $this->signage->screens()]),
                'editor_html' => $screenId > 0 ? partial('Signage::_editor-item', [
                    'entry' => $entry, 'base' => '/signage/screens/' . $screenId, 'saved' => false, 'position' => 0,
                ]) : null,
            ], 201);
        }
        Session::flash('success', 'Content added. Add it to a playlist to display it.');
        return Response::redirect('/signage/content/' . $id . '/edit');
    }

    public function editContent(Request $request): Response
    {
        $item = $this->contentItem($request);
        return $this->contentForm($item, $item);
    }

    public function updateContent(Request $request): Response
    {
        $item = $this->contentItem($request);
        [$data, $errors] = SignageValidation::content($this->contentInput($request, $item), $item['type']);
        if ($errors !== []) {
            return $this->contentForm($item, $data, $errors);
        }
        $uploaded = null;
        $file = $request->files['file'] ?? null;
        if (in_array($item['type'], ['video', 'image'], true) && $file !== null && (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            try {
                $uploaded = SignageStorage::storeUpload(is_array($file) ? $file : [], $item['type']);
            } catch (RuntimeException $error) {
                return $this->contentForm($item, $data, ['file' => $error->getMessage()]);
            }
        }
        try {
            $previous = $this->signage->updateContent((int) $item['id'], $data + ($uploaded ?? []));
        } catch (Throwable $error) {
            if ($uploaded !== null) {
                SignageStorage::delete($uploaded['stored_filename']);
            }
            throw $error;
        }
        if ($uploaded !== null && $previous['stored_filename'] !== null) {
            SignageStorage::delete($previous['stored_filename']);
        }
        Session::flash('success', 'Content saved.');
        return Response::redirect('/signage/content/' . $item['id'] . '/edit');
    }

    public function deleteContent(Request $request): Response
    {
        // JSON actions do not write session data; release the lock before waiting on storage/SQL.
        if ($request->isFetch() && session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $item = $this->contentItem($request);
        try {
            $deleted = $this->signage->deleteContent((int) $item['id']);
        } catch (InvalidArgumentException $error) {
            if ($request->isFetch()) return Response::json(['error' => $error->getMessage()], 422);
            Session::flash('error', $error->getMessage());
            return Response::redirect($request->input('from_library') === '1' ? '/signage' : '/signage/content/' . $item['id'] . '/edit');
        }
        if ($deleted['stored_filename'] !== null) {
            SignageStorage::delete($deleted['stored_filename']);
        }
        SignagePoster::deleteFor((int) $item['id']);
        if ($request->isFetch()) return Response::json(['message' => 'Content deleted.']);
        Session::flash('success', 'Content deleted.');
        return Response::redirect('/signage/content');
    }


    public function addContentToScreens(Request $request): Response
    {
        if ($request->isFetch() && session_status() === PHP_SESSION_ACTIVE) session_write_close();
        try {
            $sync = $request->input('membership_mode') === 'sync';
            $targets = SignageValidation::screenTargets($request->body['screen_ids'] ?? ($sync ? [] : null), $sync);
            $original = $sync ? SignageValidation::screenTargets($request->body['original_screen_ids'] ?? [], true) : [];
            $item = $this->contentItem($request);
            $result = $sync ? $this->signage->updateContentScreens((int) $item['id'], $targets, $original)
                : $this->signage->addContentToScreens((int) $item['id'], $targets);
        } catch (InvalidArgumentException $error) {
            if ($request->isFetch()) return Response::json(['error' => $error->getMessage()], 422);
            Session::flash('error', $error->getMessage());
            return Response::redirect('/signage');
        }
        $parts = [];
        if ($result['added'] > 0) $parts[] = 'Added to ' . $result['added'] . ($result['added'] === 1 ? ' TV' : ' TVs');
        if (($result['removed'] ?? 0) > 0) $parts[] = 'Removed from ' . $result['removed'] . ($result['removed'] === 1 ? ' TV' : ' TVs');
        $message = $parts !== [] ? implode('. ', $parts) . '.' : 'TV playlists are already up to date.';
        if ($request->isFetch()) return Response::json([
            'message' => $message,
            'screen_ids' => $this->signage->contentScreens((int) $item['id'])[(int) $item['id']] ?? [],
            'used_count' => (int) $this->signage->contentItem((int) $item['id'])['used_count'],
        ]);
        Session::flash('success', $message);
        return Response::redirect('/signage');
    }

    /** GET /signage/playlists : shared playlists that several TVs can play. */
    public function playlists(Request $request): Response
    {
        return $this->render('playlists', ['playlists' => $this->signage->sharedPlaylists(), 'errors' => []], 'playlists', 'Playlists');
    }

    /** POST /signage/playlists : create an empty shared playlist. */
    public function storePlaylist(Request $request): Response
    {
        try {
            $id = $this->signage->createSharedPlaylist($request->input('name', ''));
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
            return Response::redirect('/signage/playlists');
        }
        Session::flash('success', 'Playlist created. Add videos, images or websites to it.');
        return Response::redirect('/signage/playlists/' . $id . '/edit');
    }

    /** GET /signage/playlists/{id}/edit : a shared playlist; an old private one opens on its TV. */
    public function editPlaylist(Request $request): Response
    {
        $playlist = $this->signage->sharedPlaylist((int) $request->param('id'));
        if ($playlist === null) {
            $screen = db()->one('SELECT id FROM signage_screens WHERE playlist_id = ? ORDER BY id LIMIT 1', [(int) $request->param('id')]);
            return Response::redirect($screen ? '/signage/screens/' . $screen['id'] . '/edit#playlist' : '/signage/playlists');
        }
        return $this->playlistForm($playlist, 'playlist');
    }

    /** GET /signage/playlists/{id}/settings */
    public function playlistSettings(Request $request): Response
    {
        return $this->playlistForm($this->sharedPlaylist($request), 'settings');
    }

    /** POST /signage/playlists/{id} : rename. */
    public function updatePlaylist(Request $request): Response
    {
        $playlist = $this->sharedPlaylist($request);
        try {
            $this->signage->renameSharedPlaylist((int) $playlist['id'], $request->input('name', ''));
            Session::flash('success', 'Playlist renamed.');
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
        }
        return Response::redirect('/signage/playlists/' . $playlist['id'] . '/settings');
    }

    /** POST /signage/playlists/{id}/duplicate */
    public function duplicatePlaylist(Request $request): Response
    {
        $playlist = $this->sharedPlaylist($request);
        try {
            $id = $this->signage->duplicateSharedPlaylist((int) $playlist['id'], $request->input('name', ''));
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
            return Response::redirect('/signage/playlists/' . $playlist['id'] . '/settings');
        }
        Session::flash('success', 'Playlist copied. No TV plays the copy yet.');
        return Response::redirect('/signage/playlists/' . $id . '/edit');
    }

    /** POST /signage/playlists/{id}/delete : only when no TV plays it. */
    public function deletePlaylist(Request $request): Response
    {
        $playlist = $this->sharedPlaylist($request);
        try {
            $this->signage->deleteSharedPlaylist((int) $playlist['id']);
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
            return Response::redirect('/signage/playlists/' . $playlist['id'] . '/settings');
        }
        Session::flash('success', 'Playlist deleted. Content stays in the content library.');
        return Response::redirect('/signage/playlists');
    }

    /** POST /signage/screens/{id}/assign-playlist : playlist_id 0 = this TV's own playlist. */
    public function assignScreenPlaylist(Request $request): Response
    {
        $screen = $this->screen($request);
        $choice = $request->input('playlist_id', '');
        if (!preg_match('/^[0-9]{1,10}$/D', $choice)) {
            Session::flash('error', 'Choose a playlist.');
            return Response::redirect('/signage/screens/' . $screen['id'] . '/edit');
        }
        try {
            $this->signage->assignScreenPlaylist((int) $screen['id'], (int) $choice === 0 ? null : (int) $choice);
            Session::flash('success', (int) $choice === 0 ? $screen['name'] . ' now has its own playlist.' : $screen['name'] . ' now plays the shared playlist.');
        } catch (InvalidArgumentException | HttpException $error) {
            Session::flash('error', $error->getMessage());
        }
        return Response::redirect('/signage/screens/' . $screen['id'] . '/edit#playlist');
    }

    /** POST /signage/screens/{id}/share-playlist : keep this TV's playlist as a named shared one. */
    public function shareScreenPlaylist(Request $request): Response
    {
        $screen = $this->screen($request);
        try {
            $this->signage->shareScreenPlaylist((int) $screen['id'], $request->input('name', ''));
            Session::flash('success', 'Saved as a shared playlist. Other TVs can now use it.');
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
        }
        return Response::redirect('/signage/screens/' . $screen['id'] . '/edit#playlist');
    }

    public function addPlaylistItem(Request $request): Response
    {
        $target = $this->playlistTarget($request);
        [$data, $errors] = SignageValidation::playlistItem($request->body);
        if ($errors !== []) {
            Session::flash('error', implode(' ', array_values($errors)));
        } else {
            try {
                ($target['run'])(fn (int $id) => $this->signage->addPlaylistItem($id, (int) $data['content_id'], (int) $data['duration_seconds']));
                Session::flash('success', 'Content added to ' . $target['name'] . '.');
            } catch (InvalidArgumentException $error) {
                Session::flash('error', $error->getMessage());
            }
        }
        return Response::redirect($request->input('from_library') === '1' ? '/signage' : $target['back']);
    }

    public function updatePlaylistItem(Request $request): Response
    {
        $target = $this->playlistTarget($request);
        [$data, $errors] = SignageValidation::playlistItem($request->body, false);
        if ($errors !== []) {
            Session::flash('error', implode(' ', array_values($errors)));
        } else {
            ($target['run'])(fn (int $id) => $this->signage->updatePlaylistItem($id, (int) $request->param('item'), (int) $data['duration_seconds']));
            Session::flash('success', 'Display duration saved.');
        }
        return Response::redirect($target['back']);
    }

    public function movePlaylistItem(Request $request): Response
    {
        $target = $this->playlistTarget($request);
        try {
            ($target['run'])(fn (int $id) => $this->signage->movePlaylistItem($id, (int) $request->param('item'), (string) $request->input('direction')));
            Session::flash('success', 'Playlist order saved.');
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
        }
        return Response::redirect($target['back']);
    }

    public function deletePlaylistItem(Request $request): Response
    {
        $target = $this->playlistTarget($request);
        ($target['run'])(fn (int $id) => $this->signage->deletePlaylistItem($id, (int) $request->param('item')));
        Session::flash('success', 'Content removed from ' . $target['name'] . '.');
        return Response::redirect($target['back']);
    }

    public function saveScreenPlaylist(Request $request): Response
    {
        $target = $this->playlistTarget($request);
        try {
            $raw = $request->input('playlist_items', '');
            if (strlen($raw) > 200000) {
                throw new InvalidArgumentException('This playlist is too large.');
            }
            $entries = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($entries) || !array_is_list($entries)) {
                throw new InvalidArgumentException('Choose valid playlist items.');
            }
            ($target['run'])(fn (int $id) => $this->signage->savePlaylistItems($id, $entries, (string) $request->input('revision')));
        } catch (InvalidArgumentException | JsonException | HttpException $error) {
            if ($request->isFetch()) {
                return Response::json(['error' => $error instanceof JsonException ? 'Choose valid playlist items.' : $error->getMessage()], $error instanceof HttpException ? $error->status : 422);
            }
            Session::flash('error', $error instanceof JsonException ? 'Choose valid playlist items.' : $error->getMessage());
            return Response::redirect($target['back']);
        }
        Session::flash('success', 'Playlist saved for ' . $target['name'] . '.');
        $redirect = $target['back'];
        return $request->isFetch() ? Response::json(['redirect' => $redirect]) : Response::redirect($redirect);
    }

    /**
     * What a playlist-editing request changes: the playlist of a TV (its own, or the
     * shared one it plays), or a shared playlist opened from the Playlists tab.
     * @return array{name:string, back:string, run:callable}
     */
    private function playlistTarget(Request $request): array
    {
        if (str_starts_with($request->path, '/signage/playlists/')) {
            $playlist = $this->sharedPlaylist($request);
            return [
                'name' => (string) $playlist['name'], 'back' => '/signage/playlists/' . $playlist['id'] . '/edit#playlist',
                'run' => fn (callable $change) => $this->signage->withSharedPlaylist((int) $playlist['id'], $change),
            ];
        }
        $screen = $this->screen($request);
        return [
            'name' => (string) $screen['name'], 'back' => '/signage/screens/' . $screen['id'] . '/edit#playlist',
            'run' => fn (callable $change) => $this->signage->withScreenPlaylist((int) $screen['id'], $change),
        ];
    }

    /** @return array<string,mixed> */
    private function sharedPlaylist(Request $request): array
    {
        return $this->signage->sharedPlaylist((int) $request->param('id'))
            ?? throw HttpException::notFound('That shared playlist does not exist.');
    }

    /** @param array<string,mixed> $playlist */
    private function playlistForm(array $playlist, string $section): Response
    {
        return $this->render('playlist-form', [
            'playlist' => $playlist, 'section' => $section,
            'items' => $this->signage->playlistItems((int) $playlist['id']),
            'revision' => $this->signage->playlistRevision((int) $playlist['id']),
            'content' => $this->signage->content(),
        ], 'playlists', (string) $playlist['name']);
    }

    private function screenForm(?array $screen, array $values, array $errors = [], string $section = 'playlist'): Response
    {
        $playlistId = $screen === null ? null : $this->signage->screenPlaylistId((int) $screen['id']);
        if ($screen !== null) {
            $screen = $this->signage->screen((int) $screen['id']);
        }
        return $this->render('screen-form', [
            'screen' => $screen, 'values' => $values, 'errors' => $errors, 'section' => $section,
            'items' => $playlistId === null ? [] : $this->signage->playlistItems($playlistId),
            'revision' => $playlistId === null ? '' : $this->signage->playlistRevision($playlistId),
            'content' => $this->signage->content(),
            'currentPlaylist' => $playlistId === null ? null : $this->signage->playlist($playlistId),
            'sharedPlaylists' => $screen === null ? [] : $this->signage->sharedPlaylists(),
        ], 'screens', $screen === null ? 'Add a screen' : $screen['name'], $errors === [] ? 200 : 422);
    }

    /** Names come from uploaded filenames or website addresses, never a separate field. */
    private function contentInput(Request $request, ?array $item = null): array
    {
        $input = $request->body;
        $type = $item['type'] ?? ($input['type'] ?? '');
        if ($type === 'website') {
            $name = $request->input('source_url', '') ?: 'Website';
        } else {
            $file = $request->files['file'] ?? [];
            $filename = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
                && is_string($file['name'] ?? null) ? $file['name'] : ($item['original_filename'] ?? '');
            $name = basename(str_replace('\\', '/', (string) $filename));
            $name = preg_replace('/[\x00-\x1f\x7f]/', '', $name) ?? '';
            if ($name === '') $name = $type === 'image' ? 'Image' : 'Video';
        }
        $input['name'] = mb_substr($name, 0, 160);
        return $input;
    }

    private function contentForm(?array $item, array $values, array $errors = []): Response
    {
        return $this->render('content-form', [
            'item' => $item, 'values' => $values, 'errors' => $errors,
            'uploadLimitBytes' => SignageStorage::maxUploadBytes(),
        ], 'content', $item === null ? 'Add content' : 'Edit content', $errors === [] ? 200 : 422);
    }

    private function render(string $view, array $data, string $tab, string $title, int $status = 200): Response
    {
        return Response::html(View::render('Signage::' . $view, $data + ['tab' => $tab, 'title' => $title], 'layouts/app', $title), $status);
    }

    /** @return array<string,mixed> */
    private function screen(Request $request): array
    {
        return $this->signage->screen((int) $request->param('id'))
            ?? throw HttpException::notFound('That screen does not exist.');
    }

    /** @return array<string,mixed> */
    private function contentItem(Request $request): array
    {
        return $this->signage->contentItem((int) $request->param('id'))
            ?? throw HttpException::notFound('That content does not exist.');
    }

}
