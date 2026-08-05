<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinancePricingService;
use App\Http\Responses\Response; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class FinancePricingController extends Controller
{
    public function __construct(
        private readonly FinancePricingService $pricingService
    ) {}

    public function getPricingAnalysis($itemId): JsonResponse
    {
        try {
            $data = $this->pricingService->getItemPricingAnalysis($itemId);
            
            return Response::Success(
                $data,
                'تم استرداد تحليل التسعير بنجاح'
            );

        } catch (ModelNotFoundException $e) {
            return Response::Error(
                [],
                'المادة غير موجودة'
            );
        } catch (Throwable $th) {
            activity('Error: Fetching Pricing Analysis')->log($th);

            return Response::Error(
                [],
                'حدث خطأ أثناء حساب التسعير: ' . $th->getMessage()
            );
        }
    }

    public function updateSellingPrice(Request $request, $itemId): JsonResponse
    {
        try {
            // التحقق من أن السعر تم إرساله وهو رقم صالح (وموجب)
            $request->validate([
                'selling_price' => 'required|numeric|min:0'
            ]);

            // تحديث السعر وجلب التقرير المحدث
            $data = $this->pricingService->updateSellingPrice(
                $itemId, 
                $request->selling_price
            );
            
            return Response::Success(
                $data,
                'تم تحديث سعر البيع بنجاح'
            );

        } catch (ModelNotFoundException $e) {
            return Response::Error(
                [],
                'المادة غير موجودة'
            );
        } catch (Throwable $th) {
            activity('Error: Updating Selling Price')->log($th);

            return Response::Error(
                [],
                'حدث خطأ أثناء تحديث سعر البيع: ' . $th->getMessage()
            );
        }
    }
}