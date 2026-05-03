<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Section\StoreRequest;
use App\Http\Requests\Admin\Section\UpdateRequest;
use App\Http\Responses\Response;
use App\Services\Admin\SectionService;
use Illuminate\Http\JsonResponse;
use Throwable;

class SectionController extends Controller
{
    private SectionService $sectionService;

    public function __construct(SectionService $sectionService)
    {
        $this->sectionService = $sectionService;
    }

    public function index(): JsonResponse
    {
        $data = [];
        try {
            $data = $this->sectionService->list();
            $message = __('section.index');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Section Index')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function store(StoreRequest $request): JsonResponse
    {
        $data = [];
        try {
            $data = $this->sectionService->create($request->validated());
            $message = __('section.created');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Section Store')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function update(UpdateRequest $request, int $id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->sectionService->update($id, $request->validated());
            $message = __('section.updated');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Section Update')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->sectionService->delete($id);
            $message = __('section.deleted');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Section Delete')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

    public function show(int $id): JsonResponse
    {
        $data = [];
        try {
            $data = $this->sectionService->show($id);
            $message = __('section.found');
            return Response::Success($data, $message);
        } catch (Throwable $th) {
            activity('Error: Admin Section Show')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

}
