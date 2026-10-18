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
        $screens = $this->signage->screens();
        return $this->render('screens', [
            'screens' => $screens,
            'covers' => $this->signage->playlistCovers(array_map('intval', array_column($screens, 'playlist_id'))),
        ], 'screens', 'Signage');
    }

    public function createScreen(Request $request): Response
    {
        return $this->screenForm(null, [
            'name' => null, 'location' => null, 'playlist_id' => null,
            'orientation' => 'landscape', 'fit' => 'contain', 'muted' => 1, 'enabled' => 1,
        ]);
    }

    public function storeScreen(Request $request): Response
    {
        [$data, $errors] = SignageValidation::screen($request->body);
        if ($errors !== []) {
            return $this->screenForm(null, $data, $errors);
        }
        try {
            $id = $this->signage->createScreen($data);
        } catch (InvalidArgumentException $error) {
            return $this->screenForm(null, $data, ['playlist_id' => $error->getMessage()]);
        }
        Session::flash('success', 'Screen added. Open its player address on the TV.');
        return Response::redirect('/signage/screens/' . $id . '/edit');
    }

    public function editScreen(Request $request): Response
    {
        $screen = $this->screen($request);
        return $this->screenForm($screen, $screen);
    }

    public function updateScreen(Request $request): Response
    {
        $screen = $this->screen($request);
        [$data, $errors] = SignageValidation::screen($request->body);
        if ($errors !== []) {
            return $this->screenForm($screen, $data, $errors);
        }
        try {
            $this->signage->updateScreen((int) $screen['id'], $data);
        } catch (InvalidArgumentException $error) {
            return $this->screenForm($screen, $data, ['playlist_id' => $error->getMessage()]);
        }
        Session::flash('success', 'Screen settings saved.');
        return Response::redirect('/signage/screens/' . $screen['id'] . '/edit');
    }

    public function deleteScreen(Request $request): Response
    {
        $screen = $this->screen($request);
        $this->signage->deleteScreen((int) $screen['id']);
        Session::flash('success', 'Screen deleted. Its player address has been disabled.');
        return Response::redirect('/signage/screens');
    }

    public function rotateScreenToken(Request $request): Response
    {
        $screen = $this->screen($request);
        $this->signage->rotateScreenToken((int) $screen['id']);
        Session::flash('success', 'Player address replaced. Open the new address on the TV.');
        return Response::redirect('/signage/screens/' . $screen['id'] . '/edit');
    }

    public function content(Request $request): Response
    {
        return $this->render('content', [
            'content' => $this->signage->content(), 'usageBytes' => $this->signage->usageBytes(),
            'uploadLimitBytes' => SignageStorage::maxUploadBytes(),
        ], 'content', 'Signage content');
    }

    public function createContent(Request $request): Response
    {
        return $this->contentForm(null, ['name' => null, 'type' => 'video', 'source_url' => null]);
    }

    public function storeContent(Request $request): Response
    {
        [$data, $errors] = SignageValidation::content($request->body);
        if ($errors !== []) {
            return $this->contentForm(null, $data, $errors);
        }
        $uploaded = null;
        if ($data['type'] === 'video') {
            try {
                $uploaded = SignageStorage::storeUpload(is_array($request->files['file'] ?? null) ? $request->files['file'] : []);
            } catch (RuntimeException $error) {
                return $this->contentForm(null, $data, ['file' => $error->getMessage()]);
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
        [$data, $errors] = SignageValidation::content($request->body, $item['type']);
        if ($errors !== []) {
            return $this->contentForm($item, $data, $errors);
        }
        $uploaded = null;
        $file = $request->files['file'] ?? null;
        if ($item['type'] === 'video' && $file !== null && (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            try {
                $uploaded = SignageStorage::storeUpload(is_array($file) ? $file : []);
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
        $item = $this->contentItem($request);
        try {
            $deleted = $this->signage->deleteContent((int) $item['id']);
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
            return Response::redirect('/signage/content/' . $item['id'] . '/edit');
        }
        if ($deleted['stored_filename'] !== null) {
            SignageStorage::delete($deleted['stored_filename']);
        }
        Session::flash('success', 'Content deleted.');
        return Response::redirect('/signage/content');
    }

    public function playlists(Request $request): Response
    {
        $playlists = $this->signage->playlists();
        return $this->render('playlists', [
            'playlists' => $playlists,
            'covers' => $this->signage->playlistCovers(array_map('intval', array_column($playlists, 'id'))),
        ], 'playlists', 'Signage playlists');
    }

    public function createPlaylist(Request $request): Response
    {
        return $this->playlistForm(null, ['name' => null, 'description' => null]);
    }

    public function storePlaylist(Request $request): Response
    {
        [$data, $errors] = SignageValidation::playlist($request->body);
        if ($errors !== []) {
            return $this->playlistForm(null, $data, $errors);
        }
        $id = $this->signage->createPlaylist($data);
        Session::flash('success', 'Playlist created. Add content in the order you want it to play.');
        return Response::redirect('/signage/playlists/' . $id . '/edit');
    }

    public function editPlaylist(Request $request): Response
    {
        $playlist = $this->playlist($request);
        return $this->playlistForm($playlist, $playlist);
    }

    public function updatePlaylist(Request $request): Response
    {
        $playlist = $this->playlist($request);
        [$data, $errors] = SignageValidation::playlist($request->body);
        if ($errors !== []) {
            return $this->playlistForm($playlist, $data, $errors);
        }
        $this->signage->updatePlaylist((int) $playlist['id'], $data);
        Session::flash('success', 'Playlist saved.');
        return Response::redirect('/signage/playlists/' . $playlist['id'] . '/edit');
    }

    public function deletePlaylist(Request $request): Response
    {
        $playlist = $this->playlist($request);
        $this->signage->deletePlaylist((int) $playlist['id']);
        Session::flash('success', 'Playlist deleted. Assigned screens will wait for a new playlist.');
        return Response::redirect('/signage/playlists');
    }

    public function addPlaylistItem(Request $request): Response
    {
        $playlist = $this->playlist($request);
        [$data, $errors] = SignageValidation::playlistItem($request->body);
        if ($errors !== []) {
            Session::flash('error', implode(' ', array_values($errors)));
        } else {
            try {
                $this->signage->addPlaylistItem((int) $playlist['id'], (int) $data['content_id'], (int) $data['duration_seconds']);
                Session::flash('success', 'Content added to playlist.');
            } catch (InvalidArgumentException $error) {
                Session::flash('error', $error->getMessage());
            }
        }
        return Response::redirect('/signage/playlists/' . $playlist['id'] . '/edit');
    }

    public function updatePlaylistItem(Request $request): Response
    {
        $playlist = $this->playlist($request);
        [$data, $errors] = SignageValidation::playlistItem($request->body, false);
        if ($errors !== []) {
            Session::flash('error', implode(' ', array_values($errors)));
        } else {
            $this->signage->updatePlaylistItem((int) $playlist['id'], (int) $request->param('item'), (int) $data['duration_seconds']);
            Session::flash('success', 'Website duration saved.');
        }
        return Response::redirect('/signage/playlists/' . $playlist['id'] . '/edit');
    }

    public function movePlaylistItem(Request $request): Response
    {
        $playlist = $this->playlist($request);
        try {
            $this->signage->movePlaylistItem((int) $playlist['id'], (int) $request->param('item'), (string) $request->input('direction'));
            Session::flash('success', 'Playlist order saved.');
        } catch (InvalidArgumentException $error) {
            Session::flash('error', $error->getMessage());
        }
        return Response::redirect('/signage/playlists/' . $playlist['id'] . '/edit');
    }

    public function deletePlaylistItem(Request $request): Response
    {
        $playlist = $this->playlist($request);
        $this->signage->deletePlaylistItem((int) $playlist['id'], (int) $request->param('item'));
        Session::flash('success', 'Content removed from playlist.');
        return Response::redirect('/signage/playlists/' . $playlist['id'] . '/edit');
    }

    private function screenForm(?array $screen, array $values, array $errors = []): Response
    {
        return $this->render('screen-form', [
            'screen' => $screen, 'values' => $values, 'errors' => $errors,
            'playlists' => $this->signage->playlists(),
        ], 'screens', $screen === null ? 'Add a screen' : 'Edit screen', $errors === [] ? 200 : 422);
    }

    private function contentForm(?array $item, array $values, array $errors = []): Response
    {
        return $this->render('content-form', [
            'item' => $item, 'values' => $values, 'errors' => $errors,
            'uploadLimitBytes' => SignageStorage::maxUploadBytes(),
        ], 'content', $item === null ? 'Add content' : 'Edit content', $errors === [] ? 200 : 422);
    }

    private function playlistForm(?array $playlist, array $values, array $errors = []): Response
    {
        return $this->render('playlist-form', [
            'playlist' => $playlist, 'values' => $values, 'errors' => $errors,
            'items' => $playlist === null ? [] : $this->signage->playlistItems((int) $playlist['id']),
            'content' => $this->signage->content(),
        ], 'playlists', $playlist === null ? 'Create a playlist' : 'Edit playlist', $errors === [] ? 200 : 422);
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

    /** @return array<string,mixed> */
    private function playlist(Request $request): array
    {
        return $this->signage->playlist((int) $request->param('id'))
            ?? throw HttpException::notFound('That playlist does not exist.');
    }
}
