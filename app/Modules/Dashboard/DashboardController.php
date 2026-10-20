<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Core\Migrations;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Devices\DeviceRepository;
use App\Devices\NoteRepository;

final class DashboardController
{
    private const FEED_PAGE = 15;
    private const RECENT_ON_DASHBOARD = 5;

    /** GET / */
    public function index(Request $request): Response
    {
        $devices = new DeviceRepository();
        $counts = $devices->countsByCategory();

        $summaries = [];
        foreach (categories()->all() as $key => $category) {
            $summaries[] = ['category' => $category] + $counts[$key];
        }

        return Response::html(View::render('Dashboard::index', [
            'summaries' => $summaries,
            'attention' => $devices->withProblems(10),
            'problemTotal' => array_sum(array_column($counts, 'problem')),
            'deviceTotal' => array_sum(array_column($counts, 'total')),
            'feed' => (new NoteRepository())->recent(self::RECENT_ON_DASHBOARD, null, (new DashboardActivityVisibility())->since()),
            'pendingMigrations' => Migrations::pending(),
        ], 'layouts/app', 'Dashboard'));
    }

    /** GET /search?q= : devices of every category matching the dashboard search. */
    public function search(Request $request): Response
    {
        $query = mb_substr((string) $request->query('q', ''), 0, 100);
        $results = $query === '' ? [] : (new DeviceRepository())->search($query);

        return Response::html(View::render('Dashboard::search', [
            'query' => $query,
            'results' => $results,
        ], 'layouts/app', $query === '' ? 'Search' : "Search: $query"));
    }

    /**
     * GET /activity?before={noteId}
     * fetch(): JSON with the next page's HTML for "Show older activity".
     * Without JavaScript: a full page of older activity.
     */
    public function activity(Request $request): Response
    {
        $before = $request->query('before');
        [$feed, $nextCursor] = $this->feedPage(ctype_digit((string) $before) ? (int) $before : null);

        if ($request->isFetch()) {
            return Response::json([
                'html' => View::partial('Dashboard::_feed-items', ['feed' => $feed]),
                'next' => $nextCursor,
            ]);
        }

        return Response::html(View::render('Dashboard::activity', [
            'feed' => $feed,
            'nextCursor' => $nextCursor,
        ], 'layouts/app', 'Activity'));
    }

    /** @return array{0: list<array<string, mixed>>, 1: ?int} The notes and the cursor for the next page. */
    private function feedPage(?int $before): array
    {
        // Ask for one extra row to know whether another page exists.
        $rows = (new NoteRepository())->recent(self::FEED_PAGE + 1, $before);
        $hasMore = count($rows) > self::FEED_PAGE;
        $rows = array_slice($rows, 0, self::FEED_PAGE);
        $next = $hasMore && $rows !== [] ? (int) end($rows)['id'] : null;
        return [$rows, $next];
    }
}
