<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Warehouse\WarehouseReportService;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(WarehouseReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * جلب التقارير ديناميكياً
     */
    public function index(Request $request)
    {
        // 1. التحقق من المدخلات
        $request->validate([
            'type' => 'required|string|in:movements,demolish,audits,expiries',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);

        try {
            // 2. تمرير البيانات للسيرفس
            $report = $this->reportService->generateReport(
                $request->type,
                $request->from_date,
                $request->to_date
            );

            // 3. إرجاع الاستجابة الناجحة
            return response()->json([
                'success' => true,
                'report' => $report
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء توليد التقرير',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}