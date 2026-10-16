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
use App\Devices\DeviceServiceError;
use App\Devices\LookupRepository;
use App\Devices\NoteRepository;
use App\Devices\Status;

final class CctvController
{
    private const LABELS = [
        'area_id' => 'Area',
        'label' => 'Label',
        'location' => 'Exact location',
        'model' => 'Model',
        'serial_number' => 'Serial number',
        'ip_address' => 'IP address',
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

    /** GET /cctv?status=&area=&q= (fetch(): just the results, as JSON) */
    public function index(Request $request): Response
    {
        $area = $request->query('area');
        $filters = [
            'status' => in_array($request->query('status'), Status::ALL, true) ? $request->query('status') : null,
            'area' => $area !== null && ctype_digit($area) ? (int) $area : null,
            'q' => $request->query('q') !== null ? mb_substr((string) $request->query('q'), 0, 100) : null,
        ];
        $cameras = $this->cameras->search($filters);
        $data = [
            'category' => $this->category,
            'cameras' => $cameras,
            'groups' => CctvRepository::groupByArea($cameras),
            'counts' => $this->cameras->counts(),
            'filters' => $filters,
            'filtered' => array_filter($filters, static fn ($v) => $v !== null) !== [],
        ];

        if ($request->isFetch()) {
            return Response::json(['html' => View::partial('Cctv::_results', $data)]);
        }

        $data['areas'] = $this->areas()->options();
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
        $area = $request->query('area');
        return $this->form(null, [
            'area_id' => $area !== null && ctype_digit($area) ? (int) $area : null,
            'label' => null,
            'location' => null,
            'model' => null,
            'serial_number' => null,
            'ip_address' => null,
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

        try {
            $id = $this->cameras->create($this->category, $data, Auth::id());
        } catch (DeviceServiceError $e) {
            return $this->form(null, $data, [$e->field => $this->serialTakenMessage()]);
        }

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

        try {
            $this->cameras->update($camera, $data, Auth::id());
        } catch (DeviceServiceError $e) {
            return $this->form($camera, $data, [$e->field => $this->serialTakenMessage()]);
        }

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
            'area_id' => ['required', 'int', 'exists:cctv_areas'],
            'label' => ['required', 'max:100'],
            'location' => ['max:160'],
            'model' => ['max:100'],
            'serial_number' => ['max:64'],
            'ip_address' => ['max:45', 'ip'],
            'sd_card_gb' => ['int', 'max_value:65535'],
        ];
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
            'areas' => $this->areas()->options(),
            'models' => $this->cameras->models(),
        ], 'layouts/app', $title), $errors === [] ? 200 : 422);
    }

    /** @return array<string, mixed> */
    private function camera(Request $request): array
    {
        return $this->cameras->find((int) $request->param('id'))
            ?? throw HttpException::notFound('That camera does not exist or was deleted.');
    }

    private function areas(): LookupRepository
    {
        return new LookupRepository($this->category->lists()[0]);
    }

    private function serialTakenMessage(): string
    {
        return 'Another camera already has this serial number.';
    }
}
