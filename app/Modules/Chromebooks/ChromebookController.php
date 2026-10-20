<?php

declare(strict_types=1);

namespace App\Modules\Chromebooks;

use App\Auth\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Devices\DeviceService;
use App\Devices\DeviceServiceError;
use App\Devices\NoteRepository;
use App\Devices\Status;

final class ChromebookController
{
    private const LABELS = [
        'academic_year' => 'Year / category',
        'asset_id' => 'Asset ID',
        'serial_number' => 'Serial number',
        'model' => 'Model',
        'student_name' => 'Student',
        'student_email' => 'Email',
        'status' => 'Status',
    ];

    /** Session key for the import preview between "Check file" and "Import". */
    private const IMPORT_KEY = 'chromebook_import';

    private ChromebookCategory $category;
    private ChromebookRepository $chromebooks;

    public function __construct()
    {
        $this->category = categories()->get('chromebook');
        $this->chromebooks = new ChromebookRepository();
    }

    /** GET /chromebooks?year=&status=&q=&page= (fetch(): just the results, as JSON) */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $page = max(1, (int) ($request->query('page') ?? 1));
        $result = $this->chromebooks->search($filters, $page);
        $pages = max(1, (int) ceil($result['total'] / ChromebookRepository::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
            $result = $this->chromebooks->search($filters, $page);
        }

        $data = [
            'category' => $this->category,
            'chromebooks' => $result['rows'],
            'matching' => $result['total'],
            'page' => $page,
            'pages' => $pages,
            'counts' => $this->chromebooks->counts($filters['year']),
            'filters' => $filters,
            'filtered' => $filters['year'] !== null || $filters['status'] !== null || $filters['q'] !== null,
        ];

        if ($request->isFetch()) {
            return Response::json(['html' => View::partial('Chromebooks::_results', $data)]);
        }

        $data['years'] = $this->category->years();
        $data['yearCounts'] = $this->chromebooks->yearCounts();
        return Response::html(View::render('Chromebooks::index', $data, 'layouts/app', 'Chromebooks' . ($filters['year'] !== null ? ' ' . $this->category->yearLabel($filters['year']) : '')));
    }

    /** GET /chromebooks/export?year=&status=&q= : the filtered list as CSV */
    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $rows = $this->chromebooks->search($filters, 1, null)['rows'];
        $name = 'chromebooks-' . ($filters['year'] !== null ? strtolower($this->category->yearLabel($filters['year'])) : 'all-years') . '-' . (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d') . '.csv';
        return new Response(ChromebookCsv::export($rows, $this->category), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** GET /chromebooks/import/example : a small file showing the columns */
    public function example(Request $request): Response
    {
        $year = $this->category->yearLabel($this->year($request->query('year')));
        $body = "\xEF\xBB\xBF" . implode(',', ChromebookCsv::COLUMNS) . "\r\n"
            . "$year-01,NXJAESN0015199901,Example Student,example@pribadidepok.sch.id,Acer Chromebook 311,OK\r\n"
            . "$year-02,,Another Student,,,OK\r\n";
        return new Response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="chromebooks-example.csv"',
        ]);
    }

    /** GET /chromebooks/import?year= */
    public function importForm(Request $request): Response
    {
        Session::forget(self::IMPORT_KEY);
        return $this->importPage(null, null, $this->year($request->query('year')));
    }

    /** POST /chromebooks/import : read the file and show what would happen */
    public function importPreview(Request $request): Response
    {
        $year = $this->year($request->input('academic_year'));
        $file = $request->files['file'] ?? null;
        $error = match (true) {
            !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE => 'Choose a file first.',
            $file['error'] !== UPLOAD_ERR_OK => 'The file could not be uploaded. Try again.',
            $file['size'] > 2 * 1024 * 1024 => 'The file is too big (2 MB at most).',
            default => null,
        };
        if ($error !== null) {
            return $this->importPage(null, $error, $year, 422);
        }

        $name = basename((string) $file['name']);
        $table = ChromebookCsv::read((string) $file['tmp_name'], $name);
        if (is_string($table)) {
            return $this->importPage(null, $table, $year, 422);
        }
        $plan = ChromebookCsv::plan($table, $year, $this->chromebooks);
        if ($plan['error'] !== null) {
            return $this->importPage(null, $plan['error'], $year, 422);
        }
        Session::put(self::IMPORT_KEY, ['rows' => $plan['rows'], 'file' => $name, 'year' => $year]);
        return $this->importPage($plan + ['file' => $name], null, $year);
    }

    /** POST /chromebooks/import/confirm */
    public function importConfirm(Request $request): Response
    {
        $saved = Session::pull(self::IMPORT_KEY);
        if (!is_array($saved)) {
            Session::flash('error', 'The import expired. Choose the file again.');
            return Response::redirect('/chromebooks/import');
        }
        try {
            $result = ChromebookCsv::apply($saved['rows'], (int) $saved['year'], $this->category, $this->chromebooks, Auth::id());
        } catch (DeviceServiceError) {
            // Someone added the same asset ID or serial between preview and confirm.
            Session::flash('error', 'An asset ID or serial number in the file was just added by someone else. Nothing was imported; check the file again.');
            return Response::redirect('/chromebooks/import?year=' . (int) $saved['year']);
        }

        $parts = array_filter([
            $result['added'] ? $this->category->count($result['added']) . ' added' : null,
            $result['updated'] ? $this->category->count($result['updated']) . ' updated' : null,
        ]);
        Session::flash('success', $parts === [] ? 'Nothing to import: no changes.' : 'Import done: ' . implode(', ', $parts) . '.');
        return Response::redirect($this->yearPath((int) $saved['year']));
    }

    /** GET /chromebooks/{id} */
    public function show(Request $request): Response
    {
        $chromebook = $this->chromebook($request);
        $notes = new NoteRepository();
        $id = (int) $chromebook['id'];

        return Response::html(View::render('Chromebooks::show', [
            'category' => $this->category,
            'chromebook' => $chromebook,
            'history' => $this->chromebooks->history($id),
            'notes' => $notes->forDevice($id, manualOnly: true),
            'noteCount' => $notes->countForDevice($id, manualOnly: true),
            'google' => GoogleChromebookController::viewData($chromebook),
        ], 'layouts/app', $this->category->title($id) . ' · Chromebooks'));
    }

    /** GET /chromebooks/new?year= */
    public function create(Request $request): Response
    {
        return $this->form(null, [
            'academic_year' => $this->year($request->query('year')),
            'asset_id' => null,
            'serial_number' => null,
            'model' => null,
            'student_name' => null,
            'student_email' => null,
            'status' => null,
        ], []);
    }

    /** POST /chromebooks */
    public function store(Request $request): Response
    {
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->rules() + $this->assignRules() + ['status' => ['required', 'in:' . implode(',', $this->category->statuses())]]);
        if ($v->fails()) {
            return $this->form(null, $data, $v->errors());
        }
        $data['academic_year'] = (int) $data['academic_year'];

        try {
            $id = $this->chromebooks->create($this->category, $data, Auth::id());
        } catch (DeviceServiceError $e) {
            return $this->form(null, $data, [$e->field => $this->takenMessage($e)]);
        }

        Session::flash('success', 'Chromebook ' . ($data['asset_id'] ?? $data['serial_number'] ?? '') . ' added.');
        return Response::redirect($this->category->detailPath($id));
    }

    /** GET /chromebooks/{id}/edit */
    public function edit(Request $request): Response
    {
        $chromebook = $this->chromebook($request);
        return $this->form($chromebook, $chromebook, []);
    }

    /** POST /chromebooks/{id} */
    public function update(Request $request): Response
    {
        $chromebook = $this->chromebook($request);
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->rules());
        if ($v->fails()) {
            return $this->form($chromebook, $data + $chromebook, $v->errors());
        }
        $data['academic_year'] = (int) $data['academic_year'];

        try {
            $this->chromebooks->update($chromebook, $data, Auth::id());
        } catch (DeviceServiceError $e) {
            return $this->form($chromebook, $data + $chromebook, [$e->field => $this->takenMessage($e)]);
        }

        Session::flash('success', 'Changes saved.');
        return Response::redirect($this->category->detailPath((int) $chromebook['id']));
    }

    /** POST /chromebooks/{id}/assign (student_name, student_email; empty name = not assigned) */
    public function assign(Request $request): Response
    {
        $chromebook = $this->chromebook($request);
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->assignRules());
        if ($v->fails()) {
            Session::flash('error', implode(' ', $v->errors()));
            return Response::redirect($this->category->detailPath((int) $chromebook['id']) . '#assign');
        }

        $changed = $this->chromebooks->assign($chromebook, $data['student_name'], $data['student_email'], Auth::id());
        $label = 'Chromebook ' . $this->label($chromebook);
        Session::flash($changed ? 'success' : 'info', match (true) {
            !$changed => 'Nothing changed: it was already assigned that way.',
            $data['student_name'] === null => "$label is now not assigned to a student.",
            $data['student_name'] === $chromebook['student_name'] => 'Email saved.',
            default => "$label is now assigned to {$data['student_name']}.",
        });
        return Response::redirect($this->category->detailPath((int) $chromebook['id']));
    }

    /** POST /chromebooks/{id}/delete */
    public function destroy(Request $request): Response
    {
        $chromebook = $this->chromebook($request);
        (new DeviceService())->delete((int) $chromebook['id'], Auth::id());
        Session::flash('success', 'Chromebook ' . $this->label($chromebook) . ' was deleted.');
        return Response::redirect($this->yearPath((int) $chromebook['academic_year']));
    }

    /** @return array{year:?int, status:?string, q:?string}  year null = every year */
    private function filters(Request $request): array
    {
        return [
            'year' => $this->optionalYear($request->query('year')),
            'status' => in_array($request->query('status'), [Status::OK, ...$this->category->statuses()], true) ? $request->query('status') : null,
            'q' => $request->query('q') !== null ? mb_substr((string) $request->query('q'), 0, 100) : null,
        ];
    }

    /** A valid academic year from the request, or null for every year (the list filter). */
    private function optionalYear(mixed $value): ?int
    {
        return is_string($value) && ctype_digit($value) && in_array((int) $value, $this->category->years(), true) ? (int) $value : null;
    }

    /** A valid academic year from the request, else the newest one. */
    private function year(mixed $value): int
    {
        $years = $this->category->years();
        return is_string($value) && ctype_digit($value) && in_array((int) $value, $years, true) ? (int) $value : $years[0];
    }

    private function yearPath(int $year): string
    {
        return $this->category->path() . '?year=' . $year;
    }

    /** @param array<string, mixed> $chromebook */
    private function label(array $chromebook): string
    {
        return (string) ($chromebook['asset_id'] ?? $chromebook['serial_number'] ?? '#' . $chromebook['id']);
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'academic_year' => ['required', 'in:' . implode(',', $this->category->years())],
            'asset_id' => ['max:20'],
            'serial_number' => ['max:64'],
            'model' => ['max:100'],
        ];
    }

    /** @return array<string, list<string>> */
    private function assignRules(): array
    {
        return [
            'student_name' => ['max:120'],
            'student_email' => ['max:160', 'email'],
        ];
    }

    /**
     * @param array<string, mixed>|null $chromebook null when adding
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?array $chromebook, array $values, array $errors): Response
    {
        $title = $chromebook === null ? 'Add a Chromebook' : 'Edit Chromebook';
        return Response::html(View::render('Chromebooks::form', [
            'category' => $this->category,
            'chromebook' => $chromebook,
            'values' => $values,
            'errors' => $errors,
            'years' => $this->category->years(),
            'models' => $this->chromebooks->models(),
        ], 'layouts/app', $title), $errors === [] ? 200 : 422);
    }

    /** @param array<string, mixed>|null $plan */
    private function importPage(?array $plan, ?string $error, int $year, int $status = 200): Response
    {
        return Response::html(View::render('Chromebooks::import', [
            'category' => $this->category,
            'plan' => $plan,
            'error' => $error,
            'year' => $year,
            'years' => $this->category->years(),
        ], 'layouts/app', 'Import Chromebooks'), $status);
    }

    /** @return array<string, mixed> */
    private function chromebook(Request $request): array
    {
        return $this->chromebooks->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That Chromebook does not exist or was deleted.');
    }

    private function takenMessage(DeviceServiceError $e): string
    {
        return $e->field === 'asset_id'
            ? 'Another Chromebook already has this asset ID.'
            : 'Another Chromebook already has this serial number.';
    }
}
