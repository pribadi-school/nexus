<?php

declare(strict_types=1);

namespace App\Devices;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * One page per editable list (rooms, CCTV areas, classes): add, rename,
 * reorder, delete. Which list is decided by the URL, e.g. /studytouch/rooms.
 */
final class LookupController
{
    /** GET {list} */
    public function index(Request $request): Response
    {
        $list = $this->list($request);
        return Response::html(View::render('lists/index', [
            'list' => $list,
            'items' => (new LookupRepository($list))->allWithUsage(),
        ], 'layouts/app', $list->title));
    }

    /** POST {list} */
    public function store(Request $request): Response
    {
        $list = $this->list($request);
        $repo = new LookupRepository($list);
        $name = $this->cleanName($request->input('name'));

        if ($error = $this->nameError($list, $repo, $name)) {
            Session::flash('error', $error);
            Session::put('lookup.draft', $name);
        } else {
            $repo->add($name);
            Session::flash('success', ucfirst($list->singular) . " \"$name\" added.");
        }
        return Response::redirect($list->path);
    }

    /** POST {list}/{id}: rename, or move up/down */
    public function update(Request $request): Response
    {
        $list = $this->list($request);
        $repo = new LookupRepository($list);
        $item = $repo->find((int) $request->param('id')) ?? throw HttpException::notFound('That item was already deleted.');

        $move = $request->input('move');
        if ($move === 'up' || $move === 'down') {
            $repo->move((int) $item['id'], $move === 'up' ? -1 : 1);
            return Response::redirect($list->path . '#item-' . $item['id']);
        }

        $name = $this->cleanName($request->input('name'));
        if ($error = $this->nameError($list, $repo, $name, (int) $item['id'])) {
            Session::flash('error', $error);
        } elseif ($name !== $item['name']) {
            $repo->rename((int) $item['id'], $name);
            Session::flash('success', "Renamed \"{$item['name']}\" to \"$name\".");
        }
        return Response::redirect($list->path . '#item-' . $item['id']);
    }

    /** POST {list}/{id}/delete */
    public function destroy(Request $request): Response
    {
        $list = $this->list($request);
        $repo = new LookupRepository($list);
        $id = (int) $request->param('id');
        $item = $repo->find($id) ?? throw HttpException::notFound('That item was already deleted.');

        $used = 0;
        foreach ($repo->allWithUsage() as $row) {
            if ((int) $row['id'] === $id) {
                $used = (int) $row['used'];
            }
        }

        if ($used > 0) {
            Session::flash('error', sprintf(
                '"%s" still has %s. Move or delete them first.',
                $item['name'],
                plural($used, $list->usageNoun[0], $list->usageNoun[1]),
            ));
        } elseif (!$repo->delete($id)) {
            Session::flash('error', sprintf('"%s" is still linked to deleted %s, so it cannot be removed. Rename it instead.', $item['name'], $list->usageNoun[1]));
        } else {
            Session::flash('success', ucfirst($list->singular) . " \"{$item['name']}\" deleted.");
        }
        return Response::redirect($list->path);
    }

    private function list(Request $request): LookupList
    {
        return categories()->listForPath($request->path) ?? throw HttpException::notFound();
    }

    private function cleanName(?string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    }

    private function nameError(LookupList $list, LookupRepository $repo, string $name, ?int $exceptId = null): ?string
    {
        if ($name === '') {
            return 'Type a name for the ' . $list->singular . '.';
        }
        if (mb_strlen($name) > $list->maxLength) {
            return "That name is too long ({$list->maxLength} characters at most).";
        }
        if ($repo->nameTaken($name, $exceptId)) {
            return "There is already a {$list->singular} called \"$name\".";
        }
        return null;
    }
}
