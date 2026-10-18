<?php

declare(strict_types=1);

namespace App\Modules\StudyTouch;

use App\Auth\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Devices\DeviceService;
use App\Devices\LookupRepository;
use App\Devices\NoteRepository;
use App\Devices\Status;

final class StudyTouchController
{
    private const LABELS = [
        'room_id' => 'Room',
        'model' => 'Model',
        'firmware_version' => 'Firmware version',
        'status' => 'Status',
    ];

    private StudyTouchCategory $category;
    private StudyTouchRepository $boards;

    public function __construct()
    {
        $this->category = categories()->get('studytouch');
        $this->boards = new StudyTouchRepository();
    }

    /** GET /studytouch?status=&q= (fetch(): just the results, as JSON) */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->query('status'), Status::ALL, true) ? $request->query('status') : null,
            'model' => in_array($request->query('model'), StudyTouchCategory::MODELS, true) ? $request->query('model') : null,
            'q' => $request->query('q') !== null ? mb_substr((string) $request->query('q'), 0, 100) : null,
        ];
        $data = [
            'category' => $this->category,
            'boards' => $this->boards->search($filters),
            'counts' => $this->boards->counts(),
            'filters' => $filters,
            'filtered' => array_filter($filters, static fn ($v) => $v !== null) !== [],
        ];

        if ($request->isFetch()) {
            return Response::json(['html' => View::partial('StudyTouch::_results', $data)]);
        }

        return Response::html(View::render('StudyTouch::index', $data, 'layouts/app', 'StudyTouch'));
    }

    /** GET /studytouch/{id} */
    public function show(Request $request): Response
    {
        $board = $this->board($request);
        $notes = new NoteRepository();

        return Response::html(View::render('StudyTouch::show', [
            'category' => $this->category,
            'board' => $board,
            'notes' => $notes->forDevice((int) $board['id']),
            'noteCount' => $notes->countForDevice((int) $board['id']),
        ], 'layouts/app', $board['room_name'] . ' · StudyTouch'));
    }

    /** GET /studytouch/new */
    public function create(Request $request): Response
    {
        $room = $request->query('room');
        return $this->form(null, [
            'room_id' => $room !== null && ctype_digit($room) ? (int) $room : null,
            'model' => null,
            'firmware_version' => StudyTouchCategory::CURRENT_FIRMWARE,
            'status' => Status::OK,
        ], []);
    }

    /** POST /studytouch */
    public function store(Request $request): Response
    {
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->rules() + ['status' => ['required', 'in:ok,problem']]);
        if ($v->fails()) {
            return $this->form(null, $data, $v->errors());
        }

        $id = $this->boards->create($this->category, $data, Auth::id());

        Session::flash('success', 'Board added to ' . $this->category->title($id) . '.');
        return Response::redirect($this->category->detailPath($id));
    }

    /** GET /studytouch/{id}/edit */
    public function edit(Request $request): Response
    {
        $board = $this->board($request);
        return $this->form($board, $board, []);
    }

    /** POST /studytouch/{id} */
    public function update(Request $request): Response
    {
        $board = $this->board($request);
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->rules());
        if ($v->fails()) {
            return $this->form($board, $data, $v->errors());
        }

        $this->boards->update($board, $data, Auth::id());

        Session::flash('success', 'Changes saved.');
        return Response::redirect($this->category->detailPath((int) $board['id']));
    }

    /** POST /studytouch/{id}/delete */
    public function destroy(Request $request): Response
    {
        $board = $this->board($request);
        (new DeviceService())->delete((int) $board['id'], Auth::id());
        Session::flash('success', "The board in {$board['room_name']} was deleted.");
        return Response::redirect($this->category->path());
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'room_id' => ['required', 'int', 'exists:rooms'],
            'model' => ['required', 'in:' . implode(',', StudyTouchCategory::MODELS)],
            'firmware_version' => ['max:40'],
        ];
    }

    /**
     * @param array<string, mixed>|null $board null when adding
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?array $board, array $values, array $errors): Response
    {
        $title = $board === null ? 'Add a board' : 'Edit board';
        return Response::html(View::render('StudyTouch::form', [
            'category' => $this->category,
            'board' => $board,
            'values' => $values,
            'errors' => $errors,
            'rooms' => $this->rooms()->options(),
            'models' => StudyTouchCategory::MODELS,
            'firmwares' => $this->boards->firmwareVersions(),
        ], 'layouts/app', $title), $errors === [] ? 200 : 422);
    }

    /** @return array<string, mixed> */
    private function board(Request $request): array
    {
        return $this->boards->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That board does not exist or was deleted.');
    }

    private function rooms(): LookupRepository
    {
        return new LookupRepository($this->category->lists()[0]);
    }
}
