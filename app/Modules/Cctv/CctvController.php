<?php

declare(strict_types=1);

namespace App\Modules\Cctv;

use App\Auth\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Devices\DeviceService;
use App\Devices\NoteRepository;
use App\Devices\Status;

final class CctvController
{
    private const LABELS = [
        'label' => 'Spot',
        'model' => 'Type',
        'sd_card_gb' => 'SD card size',
        'status' => 'Status',
    ];

    private CctvCategory $category;
    private CctvRepository $cameras;

    public function __construct()
    {
        $this->category = categories()->get('cctv');
        $this->cameras = new CctvRepository();
    }

    /** GET /cctv?status=&type=&q= (fetch(): just the results, as JSON) */
    public function index(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->query('status'), Status::ALL, true) ? $request->query('status') : null,
            'type' => in_array($request->query('type'), CctvCategory::TYPES, true) ? $request->query('type') : null,
            'q' => $request->query('q') !== null ? mb_substr((string) $request->query('q'), 0, 100) : null,
        ];
        $data = [
            'category' => $this->category,
            'cameras' => $this->cameras->search($filters),
            'counts' => $this->cameras->counts(),
            'filters' => $filters,
            'filtered' => array_filter($filters, static fn ($v) => $v !== null) !== [],
        ];

        if ($request->isFetch()) {
            return Response::json(['html' => View::partial('Cctv::_results', $data)]);
        }
        return Response::html(View::render('Cctv::index', $data, 'layouts/app', 'CCTV'));
    }

    /** GET /cctv/{id} */
    public function show(Request $request): Response
    {
        $camera = $this->camera($request);
        $notes = new NoteRepository();

        return Response::html(View::render('Cctv::show', [
            'category' => $this->category,
            'camera' => $camera,
            'notes' => $notes->forDevice((int) $camera['id']),
            'noteCount' => $notes->countForDevice((int) $camera['id']),
        ], 'layouts/app', $camera['label'] . ' · CCTV'));
    }

    /** GET /cctv/new */
    public function create(Request $request): Response
    {
        return $this->form(null, [
            'label' => null,
            'model' => null,
            'sd_card_gb' => null,
            'status' => Status::OK,
        ], []);
    }

    /** POST /cctv */
    public function store(Request $request): Response
    {
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->rules() + ['status' => ['required', 'in:ok,problem']]);
        if ($v->fails()) {
            return $this->form(null, $data, $v->errors());
        }

        $id = $this->cameras->create($this->category, $this->typed($data), Auth::id());
        Session::flash('success', 'Camera ' . $data['label'] . ' added.');
        return Response::redirect($this->category->detailPath($id));
    }

    /** GET /cctv/{id}/edit */
    public function edit(Request $request): Response
    {
        $camera = $this->camera($request);
        return $this->form($camera, $camera, []);
    }

    /** POST /cctv/{id} */
    public function update(Request $request): Response
    {
        $camera = $this->camera($request);
        $v = new Validator($request->body, self::LABELS);
        $data = $v->validate($this->rules());
        if ($v->fails()) {
            return $this->form($camera, $data, $v->errors());
        }

        $this->cameras->update($camera, $this->typed($data), Auth::id());
        Session::flash('success', 'Changes saved.');
        return Response::redirect($this->category->detailPath((int) $camera['id']));
    }

    /** POST /cctv/{id}/checked (return=/path) */
    public function checked(Request $request): Response
    {
        $camera = $this->camera($request);
        (new DeviceService())->markChecked((int) $camera['id'], Auth::id());
        Session::flash('success', $camera['label'] . ' marked as checked.');
        return Response::redirect(safe_path($request->input('return'), $this->category->detailPath((int) $camera['id'])));
    }

    /** POST /cctv/{id}/delete */
    public function destroy(Request $request): Response
    {
        $camera = $this->camera($request);
        (new DeviceService())->delete((int) $camera['id'], Auth::id());
        Session::flash('success', "Camera {$camera['label']} was deleted.");
        return Response::redirect($this->category->path());
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'label' => ['required', 'max:100'],
            'model' => ['required', 'in:' . implode(',', CctvCategory::TYPES)],
            'sd_card_gb' => ['int', 'in:' . implode(',', CctvCategory::SD_SIZES)],
        ];
    }

    /**
     * @param array<string, mixed> $data validated form values
     * @return array<string, mixed>
     */
    private function typed(array $data): array
    {
        $data['sd_card_gb'] = $data['sd_card_gb'] === null ? null : (int) $data['sd_card_gb'];
        return $data;
    }

    /**
     * @param array<string, mixed>|null $camera null when adding
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function form(?array $camera, array $values, array $errors): Response
    {
        $title = $camera === null ? 'Add a camera' : 'Edit camera';
        return Response::html(View::render('Cctv::form', [
            'category' => $this->category,
            'camera' => $camera,
            'values' => $values,
            'errors' => $errors,
        ], 'layouts/app', $title), $errors === [] ? 200 : 422);
    }

    /** @return array<string, mixed> */
    private function camera(Request $request): array
    {
        return $this->cameras->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That camera does not exist or was deleted.');
    }
}
