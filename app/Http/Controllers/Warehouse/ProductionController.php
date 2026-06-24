<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\ProductionStatusEnum;
use App\Models\ProductionOrder;
use Throwable;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Responses\Response;
use App\Services\Warehouse\ProductionService;

class ProductionController extends Controller
{
    public function __construct(
        private readonly ProductionService $service
    ) {}

    public function index(): JsonResponse {

        $data = [];

        try {
            // تعديل boms إلى bomAsFinal لتتوافق مع الموديل عندك وتجنب الـ N+1 Query
            $orders = ProductionOrder::query()
                ->with([
                    'item.bomAsFinal.basicItem', // تم التعديل هنا 
                    'warehouse', 
                    'production', 
                    'materials.item',           
                    'materials.shipmentItem'    
                ])
                ->latest()
                ->get();

            // تحويل البيانات لتناسب الواجهة بدقة بناءً على حالة الطلب
            $data = $orders->map(function ($order) {
                
                $isPreparedOrBeyond = in_array($order->status, [
                    ProductionStatusEnum::MATERIALS_RESERVED->value,
                    ProductionStatusEnum::SENT_TO_PRODUCTION->value,
                    ProductionStatusEnum::IN_PRODUCTION->value,
                    ProductionStatusEnum::PAUSED->value,
                    ProductionStatusEnum::COMPLETED->value,
                ]);

                if ($isPreparedOrBeyond) {
                    // 1. الطلب محضر وجاهز -> نعرض الدفعات المخصصة له من جدول الـ materials
                    $order->required_materials = $order->materials->map(function ($material) {
                        return [
                            'item_id'            => $material->item_id,
                            'item_name'          => $material->item->name ?? 'مادة غير معروفة',
                            'total_required'     => $material->required_quantity,
                            'total_consumed'     => $material->consumed_quantity,
                            'is_allocated'       => true,
                            'batch_details'      => [
                                'shipment_item_id' => $material->shipment_item_id,
                                'expiry_date'      => $material->shipmentItem->expiry_date ?? 'بدون تاريخ',
                                'quantity_to_pull' => $material->required_quantity
                            ]
                        ];
                    });
                } else {
                    // 2. الطلب جديد -> نحسب المتطلبات من علاقة bomAsFinal المحملة مسبقاً
                    $order->required_materials = $order->item->bomAsFinal->map(function ($bom) use ($order) {
                        return [
                            'item_id'            => $bom->basic_item_id,
                            'item_name'          => $bom->basicItem->name ?? 'مادة غير معروفة',
                            'total_required'     => $bom->basic_item_quantity * $order->quantity, 
                            'total_consumed'     => 0,
                            'is_allocated'       => false,
                            'batch_details'      => null 
                        ];
                    });
                }
                // تثبيت تاريخ الإنشاء والتعديل وتنسيقهما لتسهيل القراءة في الـ API
                $order->created_at_formatted = $order->created_at?->format('Y-m-d H:i:s');
                $order->updated_at_formatted = $order->updated_at?->format('Y-m-d H:i:s');

                // تنظيف الاستجابة من العلاقات الخام بعد تحويرها
                unset($order->materials);
                if (isset($order->item)) {
                    unset($order->item->bomAsFinal);
                }

                return $order;
            });

            return Response::Success(
                $data,
                'Production orders fetched successfully with dynamic material allocations'
            );

        } catch (Throwable $th) {

            activity('Error: Warehouse Index Production Order')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    public function show($id): JsonResponse {

        $data = [];

        try {
            // تعديل العلاقة هنا أيضاً لضمان عمل شاشة العرض الفردي
            $data = ProductionOrder::query()
                ->with([
                    'item.bomAsFinal.basicItem', 
                    'warehouse', 
                    'production', 
                    'materials.item', 
                    'materials.shipmentItem'
                ])
                ->findOrFail($id);

            return Response::Success(
                $data,
                'Production order fetched successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Show Production Order')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }



    
    /*
    |--------------------------------------------------------------------------
    | Reserve Materials
    |--------------------------------------------------------------------------
    */

    public function reserve($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service
                ->reserveMaterials($id);

            return Response::Success(
                $data,
                'Materials reserved successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Reserve Materials')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Send To Production
    |--------------------------------------------------------------------------
    */

    public function send($id): JsonResponse
    {
        $data = [];

        try {

            $data = $this->service
                ->sendToProduction($id);

            return Response::Success(
                $data,
                'Order sent to production successfully'
            );

        } catch (Throwable $th) {

            activity('Error: Send To Production')
                ->log($th);

            return Response::Error(
                $data,
                $th->getMessage()
            );
        }
    }
}


// namespace App\Http\Controllers\Warehouse;

// use App\Enums\ProductionStatusEnum;
// use App\Models\ProductionOrder;
// use Throwable;
// use Illuminate\Http\JsonResponse;
// use App\Http\Controllers\Controller;
// use App\Http\Responses\Response;
// use App\Services\Warehouse\ProductionService;

// class ProductionController extends Controller
// {
//     public function __construct(
//         private readonly ProductionService $service
//     ) {}

//     public function index(): JsonResponse {

//         $data = [];

//         try {

//             $data = ProductionOrder::query()
//                 ->with(['item', 'warehouse', 'production', 'materials'])
// //                ->where('status', '=', ProductionStatusEnum::APPROVED_BY_MANAGER->value)
//                 ->get();

//             return Response::Success(
//                 $data,
//                 'Production order with approved by manager status fetched successfully'
//             );

//         } catch (Throwable $th) {

//             activity('Error: Warehouse Index Production Order')
//                 ->log($th);

//             return Response::Error(
//                 $data,
//                 $th->getMessage()
//             );
//         }
//     }

//     public function show($id): JsonResponse {

//         $data = [];

//         try {

//             $data = ProductionOrder::query()->findOrFail($id);

//             return Response::Success(
//                 $data,
//                 'Production order fetched successfully'
//             );

//         } catch (Throwable $th) {

//             activity('Error: Show Production Order')
//                 ->log($th);

//             return Response::Error(
//                 $data,
//                 $th->getMessage()
//             );
//         }
//     }


//     /*
//     |--------------------------------------------------------------------------
//     | Reserve Materials
//     |--------------------------------------------------------------------------
//     */

//     public function reserve($id): JsonResponse
//     {
//         $data = [];

//         try {

//             $data = $this->service
//                 ->reserveMaterials($id);

//             return Response::Success(
//                 $data,
//                 'Materials reserved successfully'
//             );

//         } catch (Throwable $th) {

//             activity('Error: Reserve Materials')
//                 ->log($th);

//             return Response::Error(
//                 $data,
//                 $th->getMessage()
//             );
//         }
//     }

//     /*
//     |--------------------------------------------------------------------------
//     | Send To Production
//     |--------------------------------------------------------------------------
//     */

//     public function send($id): JsonResponse
//     {
//         $data = [];

//         try {

//             $data = $this->service
//                 ->sendToProduction($id);

//             return Response::Success(
//                 $data,
//                 'Order sent to production successfully'
//             );

//         } catch (Throwable $th) {

//             activity('Error: Send To Production')
//                 ->log($th);

//             return Response::Error(
//                 $data,
//                 $th->getMessage()
//             );
//         }
//     }
// }
