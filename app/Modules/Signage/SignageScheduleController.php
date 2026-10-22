<?php

declare(strict_types=1);

namespace App\Modules\Signage;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use InvalidArgumentException;

/** Optional schedules that put a shared playlist on chosen TVs for a time window. */
final class SignageScheduleController
{
    private SignageSchedules $schedules;
    private SignageRepository $signage;

    public function __construct()
    {
        $this->schedules = new SignageSchedules();
        $this->signage = new SignageRepository();
    }

    /** GET /signage/schedules */
    public function index(Request $request): Response
    {
        return $this->render('schedules', ['schedules' => $this->schedules->all(), 'hasPlaylists' => $this->signage->sharedPlaylists() !== []], 'Schedules');
    }

    /** GET /signage/schedules/create */
    public function create(Request $request): Response
    {
        return $this->form(null, [
            'name' => '', 'playlist_id' => $request->query('playlist', ''), 'screen_ids' => [], 'mode' => 'weekly',
            'days' => [1, 2, 3, 4, 5], 'start_time' => '07:00', 'end_time' => '07:30', 'enabled' => 1,
        ]);
    }

    /** POST /signage/schedules */
    public function store(Request $request): Response
    {
        return $this->save(null, $request);
    }

    /** GET /signage/schedules/{id}/edit */
    public function edit(Request $request): Response
    {
        $schedule = $this->schedule($request);
        $once = $schedule['start_date'] !== null && $schedule['start_date'] === $schedule['end_date'];
        return $this->form($schedule, [
            'name' => $schedule['name'], 'playlist_id' => (string) $schedule['playlist_id'],
            'screen_ids' => array_column($schedule['screens'], 'id'), 'mode' => $once ? 'once' : 'weekly',
            'date' => $once ? $schedule['start_date'] : '',
            'days' => array_values(array_filter(array_keys(SignageSchedules::DAYS), static fn (int $n): bool => ((int) $schedule['days'] & (1 << ($n - 1))) !== 0)),
            'start_date' => $once ? '' : (string) $schedule['start_date'], 'end_date' => $once ? '' : (string) $schedule['end_date'],
            'start_time' => substr((string) $schedule['start_time'], 0, 5), 'end_time' => substr((string) $schedule['end_time'], 0, 5),
            'enabled' => (int) $schedule['enabled'],
        ]);
    }

    /** POST /signage/schedules/{id} */
    public function update(Request $request): Response
    {
        return $this->save($this->schedule($request), $request);
    }

    /** POST /signage/schedules/{id}/toggle : switch it on or off without losing its settings. */
    public function toggle(Request $request): Response
    {
        $schedule = $this->schedule($request);
        $on = !(bool) $schedule['enabled'];
        $this->schedules->setEnabled((int) $schedule['id'], $on);
        Session::flash('success', $on ? 'Schedule turned on.' : 'Schedule turned off. Its TVs play their normal playlists.');
        return Response::redirect('/signage/schedules');
    }

    /** POST /signage/schedules/{id}/delete */
    public function delete(Request $request): Response
    {
        $schedule = $this->schedule($request);
        $this->schedules->delete((int) $schedule['id']);
        Session::flash('success', 'Schedule deleted.');
        return Response::redirect('/signage/schedules');
    }

    /** @param array<string,mixed>|null $schedule */
    private function save(?array $schedule, Request $request): Response
    {
        $screens = array_map(static fn (array $s): int => (int) $s['id'], $this->signage->screens());
        $playlists = array_map(static fn (array $p): int => (int) $p['id'], $this->signage->sharedPlaylists());
        [$data, $screenIds, $errors] = SignageSchedules::validate($request->body, $screens, $playlists);
        if ($errors === []) {
            try {
                $id = $this->schedules->save($schedule === null ? null : (int) $schedule['id'], $data, $screenIds);
                Session::flash('success', $schedule === null ? 'Schedule created.' : 'Schedule saved.');
                return Response::redirect('/signage/schedules');
            } catch (InvalidArgumentException $error) {
                $errors['playlist_id'] = $error->getMessage();
            }
        }
        $values = $request->body + ['mode' => 'weekly'];
        $values['screen_ids'] = array_map('intval', (array) ($request->body['screen_ids'] ?? []));
        $values['days'] = array_map('intval', (array) ($request->body['days'] ?? []));
        return $this->form($schedule, $values, $errors);
    }

    /** @param array<string,mixed>|null $schedule @param array<string,mixed> $values @param array<string,string> $errors */
    private function form(?array $schedule, array $values, array $errors = []): Response
    {
        return $this->render('schedule-form', [
            'schedule' => $schedule, 'values' => $values, 'errors' => $errors,
            'screens' => $this->signage->screens(), 'playlists' => $this->signage->sharedPlaylists(),
        ], $schedule === null ? 'New schedule' : (string) $schedule['name'], $errors === [] ? 200 : 422);
    }

    /** @param array<string,mixed> $data */
    private function render(string $view, array $data, string $title, int $status = 200): Response
    {
        return Response::html(View::render('Signage::' . $view, $data + ['tab' => 'schedules', 'title' => $title], 'layouts/app', $title), $status);
    }

    /** @return array<string,mixed> */
    private function schedule(Request $request): array
    {
        return $this->schedules->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That schedule does not exist.');
    }
}
