<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinancialReportService;
use App\Http\Responses\Response; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class FinancialReportController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reportService
    ) {}

    public function getSalesProfitsReport(Request $request): JsonResponse
    {
        try {
            // التحقق من صحة التواريخ في حال تم إرسالها
            $request->validate([
                'start_date' => 'nullable|date|before_or_equal:today',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
            ]);

            $data = $this->reportService->getSalesAndProfitReport(
                $request->start_date,
                $request->end_date
            );
            
            return Response::Success(
                $data,
                'تم استرداد التقرير المالي للمبيعات والأرباح بنجاح'
            );

        } catch (Throwable $th) {
            activity('Error: Finance Sales Report')->log($th);

            return Response::Error(
                [],
                'حدث خطأ أثناء إعداد التقرير المالي: ' . $th->getMessage()
            );
        }
    }
}