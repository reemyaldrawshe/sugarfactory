<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\ProductionCostService;
use App\Http\Responses\Response; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;

class FinanceProductionController extends Controller
{
    public function __construct(
        private readonly ProductionCostService $costService
    ) {}

    public function getProductionCosts(Request $request): JsonResponse
    {
        try {
            // 💡 إضافة item_id للتحقق
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
                'item_id'    => 'nullable|exists:items,id' // يجب أن تكون المادة موجودة في جدول items
            ]);

            $report = $this->costService->getProductionCostReport(
                $request->start_date,
                $request->end_date,
                $request->item_id // 👈 تمرير الـ id للـ Service
            );

            return Response::Success(
                $report,
                'تم جلب تقرير تكاليف الإنتاج بنجاح'
            );

        } catch (Throwable $th) {
            activity('Error: Fetching Production Costs')->log($th);

            return Response::Error(
                [],
                'حدث خطأ أثناء جلب التقرير: ' . $th->getMessage()
            );
        }
    }
}