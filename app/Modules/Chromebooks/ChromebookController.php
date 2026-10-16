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
use App\Devices\LookupRepository;
use App\Devices\NoteRepository;
use App\Devices\Status;

final class ChromebookController
{
    private const LABELS = [
        'serial_number' => 'Serial number',
        'model' => 'Model',
        'student_name' => 'Student',
        'class_id' => 'Class',
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

    /** GET /chromebooks?status=&class=&q=&page= (fetch(): just the results, as JSON) */
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
            'counts' => $this->chromebooks->counts(),
            'filters' => $filters,
            'filtered' => array_filter($filters, static fn ($v) => $v !== null) !== [],
        ];

        if ($request->isFetch()) {
            return Response::json(['html' => View::partial('Chromebooks::_results', $data)]);
        }

        $data['classes'] = $this->classes()->options();
        return Response::html(View::render('Chromebooks::index', $data, 'layouts/app', 'Chromebooks'));
    }

    /** GET /chromebooks/export?status=&class=&q= : the filtered list as CSV */
    public function export(Request $request): Response
    {
        $rows = $this->chromebooks->search($this->filters($request), 1, null)['rows'];
        $name = 'chromebooks-' . (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d') . '.csv';
        return new Response(ChromebookCsv::export($rows, $this->category), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** GET /chromebooks/import/example : a small file showing the columns */
    public function example(Request $request): Response
    {
        $classes = array_column($this->classes()->options(), 'name');
        $body = "\xEF\xBB\xBF" . implode(',', ChromebookCsv::COLUMNS) . "\r\n"
            . 'NXK6ESN00999901,Example Student,' . ($classes[0] ?? '7A') . ",Acer Chromebook 311,OK\r\n"
            . "NXK6ESN00999902,,,Acer Chromebook 311,OK\r\n";
        return new Response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="chromebooks-example.csv"',
        ]);
    }

    /** GET /chromebooks/import */
    public function importForm(Request $request): Response
    {
        Session::forget(self::IMPORT_KEY);
        return $this->importPage(null, null);
    }

    /** POST /chromebooks/import : read the file and show what would happen */
    public function importPreview(Request $request): Response
    {
        $file = $request->files['file'] ?? null;
        $error = match (true) {
            !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE => 'Choose a CSV file first.',
            $file['error'] !== UPLOAD_ERR_OK => 'The file could not be uploaded. Try again.',
            $file['size'] > 2 * 1024 * 1024 => 'The file is too big (2 MB at most).',
            default => null,
        };
        if ($error !== null) {
            return $this->importPage(null, $error, 422);
        }

        $plan = ChromebookCsv::plan((string) file_get_contents($file['tmp_name']), $this->chromebooks);
        if ($plan['error'] !== null) {
            return $this->importPage(null, $plan['error'], 422);
        }
        Session::put(self::IMPORT_KEY, ['rows' => $plan['rows'], 'file' => basename((string) $file['name'])]);
        return $this->importPage($plan + ['file' => basename((string) $file['name'])], null);
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
            $result = ChromebookCsv::apply($saved['rows'], $this->category, $this->chromebooks, Auth::id());
        } catch (DeviceServiceError) {
            // Someone added the same serial between preview and confirm.
            Session::flash('error', 'A serial number in the file was just added by someone else. Nothing was imported; check the file again.');
            return Response::redirect('/chromebooks/import');
        }

        $parts = array_filter([
            $result['added'] ? $this->category->count($result['added']) . ' added' : null,
            $result['updated'] ? $this->category->count($result['updated']) . ' updated' : null,
        ]);
        Session::flash('success', $parts === [] ? 'Nothing to import: no changes.' : 'Import done: ' . implode(', ', $parts) . '.');
        return Response::redirect($this->category->path());
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
            'classes' => $this->classes()->options(),
            'notes' => $notes->forDevice($id),
            'noteCount' => $notes->countForDevice($id),
            'assignErrors' => [],
        ], 'layouts/app', $this->category->title($id) . ' · Chromebooks'));
    }

    /** GET /chromebooks/new */
    public function create(Request $request): Response
    {
        $class = $request->query('class');
        return $this->form(null, [
            'serial_number' => null,
            'model' => null,
            'student_name' => null,
            'class_id' => $class !== null && ctype_digit($class) ? (int) $class : null,
            'status' => Status::OK,
        ], []);
    }

    /** POST /chromebooks */
    public function store(Request $request): Response
    {
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->rules() + $this->assignRules() + ['status' => ['required', 'in:ok,problem']]);
        if ($v->fails()) {
            return $this->form(null, $data, $v->errors());
        }
        $data['class_id'] = $data['class_id'] === null ? null : (int) $data['class_id'];

        try {
            $id = $this->chromebooks->create($this->category, $data, Auth::id());
        } catch (DeviceServiceError $e) {
            return $this->form(null, $data, [$e->field => $this->serialTakenMessage()]);
        }

        Session::flash('success', 'Chromebook ' . $data['serial_number'] . ' added.');
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

        try {
            $this->chromebooks->update($chromebook, $data, Auth::id());
        } catch (DeviceServiceError $e) {
            return $this->form($chromebook, $data + $chromebook, [$e->field => $this->serialTakenMessage()]);
        }

        Session::flash('success', 'Changes saved.');
        return Response::redirect($this->category->detailPath((int) $chromebook['id']));
    }

    /** POST /chromebooks/{id}/assign (student_name, class_id; both empty = not assigned) */
    public function assign(Request $request): Response
    {
        $chromebook = $this->chromebook($request);
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->assignRules());
        if ($v->fails()) {
            Session::flash('error', implode(' ', $v->errors()));
            return Response::redirect($this->category->detailPath((int) $chromebook['id']) . '#assign');
        }

        $changed = $this->chromebooks->assign(
            $chromebook,
            $data['student_name'],
            $data['class_id'] === null ? null : (int) $data['class_id'],
            Auth::id(),
        );
        Session::flash($changed ? 'success' : 'info', match (true) {
            !$changed => 'Nothing changed: it was already assigned that way.',
            $data['student_name'] === null => 'Chromebook ' . $chromebook['serial_number'] . ' is now not assigned to a student.',
            default => 'Chromebook ' . $chromebook['serial_number'] . ' is now assigned to ' . $data['student_name'] . '.',
        });
        return Response::redirect($this->category->detailPath((int) $chromebook['id']));
    }

    /** POST /chromebooks/{id}/delete */
    public function destroy(Request $request): Response
    {
        $chromebook = $this->chromebook($request);
        (new DeviceService())->delete((int) $chromebook['id'], Auth::id());
        Session::flash('success', 'Chromebook ' . ($chromebook['serial_number'] ?? '#' . $chromebook['id']) . ' was deleted.');
        return Response::redirect($this->category->path());
    }

    /** @return array{status:?string, class:?int, q:?string} */
    private function filters(Request $request): array
    {
        $class = $request->query('class');
        return [
            'status' => in_array($request->query('status'), Status::ALL, true) ? $request->query('status') : null,
            // "none" = Chromebooks without a class.
            'class' => $class === 'none' ? 0 : ($class !== null && ctype_digit($class) ? (int) $class : null),
            'q' => $request->query('q') !== null ? mb_substr((string) $request->query('q'), 0, 100) : null,
        ];
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'serial_number' => ['required', 'max:64'],
            'model' => ['max:100'],
        ];
    }

    /** @return array<string, list<string>> */
    private function assignRules(): array
    {
        return [
            'student_name' => ['max:120'],
            'class_id' => ['int', 'exists:classes'],
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
            'classes' => $this->classes()->options(),
            'models' => $this->chromebooks->models(),
        ], 'layouts/app', $title), $errors === [] ? 200 : 422);
    }

    /** @param array<string, mixed>|null $plan */
    private function importPage(?array $plan, ?string $error, int $status = 200): Response
    {
        return Response::html(View::render('Chromebooks::import', [
            'category' => $this->category,
            'plan' => $plan,
            'error' => $error,
        ], 'layouts/app', 'Import Chromebooks'), $status);
    }

    /** @return array<string, mixed> */
    private function chromebook(Request $request): array
    {
        return $this->chromebooks->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That Chromebook does not exist or was deleted.');
    }

    private function classes(): LookupRepository
    {
        return new LookupRepository($this->category->lists()[0]);
    }

    private function serialTakenMessage(): string
    {
        return 'Another Chromebook already has this serial number.';
    }
}
