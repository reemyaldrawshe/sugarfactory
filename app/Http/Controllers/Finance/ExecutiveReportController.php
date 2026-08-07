<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\ExecutiveFinancialReportService;
use App\Http\Responses\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class ExecutiveReportController extends Controller
{
    public function __construct(
        private readonly ExecutiveFinancialReportService $reportService
    ) {}

    /**
     * استخراج التقرير المالي التنفيذي العام
     */
    public function getExecutiveReport(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query('start_date');
            $endDate = $request->query('end_date');

            $reportData = $this->reportService->generateExecutiveReport($startDate, $endDate);

            return Response::Success(
                $reportData,
                'تم استخراج التقرير المالي التنفيذي للمعمل بنجاح'
            );
        } catch (Throwable $th) {
            return Response::Error(
                [],
                'حدث خطأ أثناء استخراج التقرير المالي: ' . $th->getMessage()
            );
        }
    }
}