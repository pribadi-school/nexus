<?php

declare(strict_types=1);

namespace App\Devices;

use App\Auth\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use RuntimeException;

/** Endpoints shared by every category: forwarding, the status switch, the notes log. */
final class DeviceController
{
    public const NOTE_MAX = 2000;

    /** GET /devices/{id}: forwards to the device's page in its own category. */
    public function show(Request $request): Response
    {
        [$device, $category] = $this->load($request);
        return Response::redirect($category->detailPath((int) $device['id']));
    }

    /**
     * POST /devices/{id}/status  (category status, return=/path)
     * fetch(): JSON for the switch. Plain form: back to `return`.
     */
    public function status(Request $request): Response
    {
        [$device, $category] = $this->load($request);
        $status = (string) $request->input('status');
        if (!$category->acceptsStatus($status)) {
            throw new HttpException(422, 'Unknown status.');
        }

        $id = (int) $device['id'];
        $changed = (new DeviceService())->changeStatus($category, $device, $status, Auth::id());
        $label = $category->statusLabel($status);
        $title = $category->title($id);

        if ($request->isFetch()) {
            return Response::json([
                'status' => $status,
                'label' => $label,
                'changed' => $changed,
                'message' => "$title: $label",
                'noteUrl' => $category->detailPath($id) . '#add-note',
            ]);
        }

        Session::flash('success', $changed ? "$title is now marked \"$label\"." : "$title was already \"$label\".");
        return Response::redirect(safe_path($request->input('return'), $category->detailPath($id)));
    }

    /** POST /devices/{id}/notes  (kind, body, optional set_status) */
    public function addNote(Request $request): Response
    {
        [$device, $category] = $this->load($request);
        $id = (int) $device['id'];
        $back = $category->detailPath($id) . '#notes';

        $plainNote = $category->key() === 'chromebook';
        $kind = $plainNote ? NoteKind::NOTE : (string) $request->input('kind');
        $body = trim(str_replace("\r\n", "\n", (string) ($request->body['body'] ?? '')));
        $setStatus = $plainNote ? null : $request->input('set_status');

        $error = match (true) {
            !in_array($kind, $category->noteKinds(), true) => 'Choose what kind of note this is.',
            $body === '' => 'Write what happened before saving the note.',
            mb_strlen($body) > self::NOTE_MAX => 'That note is too long (' . self::NOTE_MAX . ' characters at most).',
            $setStatus !== null && $setStatus !== '' && (!is_string($setStatus) || !$category->acceptsStatus($setStatus)) => 'Choose a valid status, or keep the current status.',
            default => null,
        };
        if ($error !== null) {
            Session::flash('error', $error);
            Session::put('note.draft', ['device' => $id, 'kind' => $kind, 'body' => $body, 'set_status' => $setStatus]);
            return Response::redirect($category->detailPath($id) . '#add-note');
        }

        $image = null;
        $file = $request->files['image'] ?? [];
        if (is_array($file) && NoteImage::submitted($file)) {
            try {
                $image = NoteImage::store($file);
            } catch (RuntimeException $e) {
                Session::flash('error', $e->getMessage());
                Session::put('note.draft', ['device' => $id, 'kind' => $kind, 'body' => $body, 'set_status' => $setStatus]);
                return Response::redirect($category->detailPath($id) . '#add-note');
            }
        }

        $service = new DeviceService();
        try {
            $statusChanged = db()->transaction(function () use ($service, $category, $device, $id, $kind, $body, $setStatus, $image): bool {
                $service->addNote($id, Auth::id(), $kind, $body, null, $image);
                if (is_string($setStatus) && $category->acceptsStatus($setStatus)) {
                    return $service->changeStatus($category, $device, $setStatus, Auth::id());
                }
                return false;
            });
        } catch (\Throwable $e) {
            NoteImage::delete($image); // do not leave an orphan file behind
            throw $e;
        }

        Session::flash('success', $statusChanged
            ? 'Note added, and the status is now "' . $category->statusLabel((string) $setStatus) . '".'
            : 'Note added.');
        return Response::redirect($back);
    }

    /** GET /devices/notes/{id}/image : a note's picture (signed-in users only). */
    public function noteImage(Request $request): Response
    {
        $filename = (string) db()->value(
            'SELECT n.image_filename FROM device_notes n JOIN devices d ON d.id = n.device_id
             WHERE n.id = ? AND d.deleted_at IS NULL',
            [(int) $request->param('id')],
        );
        $path = $filename !== '' ? NoteImage::path($filename) : '';
        if ($path === '' || !is_file($path)) {
            throw HttpException::notFound('This note has no image.');
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => NoteImage::mime($filename),
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: Category} */
    private function load(Request $request): array
    {
        $device = (new DeviceRepository())->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That device does not exist or was deleted.');
        $category = categories()->find((string) $device['category'])
            ?? throw HttpException::notFound('That device belongs to a category that is turned off.');
        return [$device, $category];
    }
}
