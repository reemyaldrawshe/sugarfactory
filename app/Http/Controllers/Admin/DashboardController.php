<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Admin\DashboardService;
use Illuminate\Http\JsonResponse;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function stats(): JsonResponse
    {
        $data = [];
        try {
            $data = $this->dashboardService->getStats();
            return Response::Success($data, __('auth.dashboard_stats'));
        } catch (Throwable $th) {
            activity('Error: Admin Dashboard')->log($th);
            return Response::Error($data, $th->getMessage());
        }
    }

}

