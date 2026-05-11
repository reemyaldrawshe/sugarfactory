<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StartProductionOrderRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Services\Production\ProductionOrderService;
use App\Http\Requests\Production\WarehouseApproveProductionOrderRequest;
 use App\Http\Requests\Production\ApproveProductionOrderRequest;
 use App\Http\Requests\Production\PauseProductionOrderRequest;
 use App\Http\Requests\Production\ResumeProductionOrderRequest;
 use App\Http\Requests\Production\CompleteProductionOrderRequest;
 use App\Http\Requests\Production\SendMaterialsToProductionRequest;
 use App\Http\Requests\Production\MaterialRequestsRequest;
 use App\Http\Requests\Production\AllProductionOrdersRequest;

class ProductionOrderController extends Controller
{
    public function __construct(private ProductionOrderService $service) {}

    public function store(StoreProductionOrderRequest $request)
    {
        $order = $this->service->store($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء أمر الإنتاج',
            'data' => $order
        ]);
    }

   

public function managerDecision($id, ApproveProductionOrderRequest $request)
{
    $order = $this->service->managerDecision($id, $request->validated());

  return response()->json([
   'status' => true,
        'message' => 'تم اتخاذ القرار بنجاح',
    'data' => [
        'id' => $order->id,
        'status' => $order->status,
      
    ]
]);
}


  public function warehouseApprove($id, WarehouseApproveProductionOrderRequest $request)
{
    $order = $this->service->warehouseApprove($id, $request->validated());

    $materials = \App\Models\ProductionOrderMaterial::with([
        'item',
        'shipmentItem'
    ])
    ->where('production_order_id', $order->id)
    ->get();

    return response()->json([
        'status' => true,
        'message' => 'تم صرف المواد وتحضير أمر الإنتاج',

        'data' => [
            'order' => [
                'id' => $order->id,
                'product_id' => $order->item_id,
                'quantity' => $order->quantity,
                'status' => $order->status,
            ],

            'materials' => $materials->map(function ($material) {

                return [
                    'material_id' => $material->item_id,

                    'material_name' => $material->item->name ?? null,

                    'shipment_item_id' => $material->shipment_item_id,

                    'required_quantity' => $material->required_quantity,

                    'consumed_quantity' => $material->consumed_quantity,

                    'batch' => [
                        'id' => $material->shipmentItem->id ?? null,

                        'expiry_date' => $material->shipmentItem->expiry_date ?? null,

                        'remaining_quantity' =>
                            $material->shipmentItem->quantity_received ?? null,
                    ],
                ];
            }),
        ]
    ]);
}

public function start($id, StartProductionOrderRequest $request)
{
    $order = $this->service->start($id);

    return response()->json([
        'status' => true,
        'message' => 'تم بدء عملية الإنتاج',
        'data' => [
            'id' => $order->id,
            'status' => $order->status,
           
        ]
    ]);
}

public function pause($id, PauseProductionOrderRequest $request)
{
    $order = $this->service->pause($id, $request->validated());

    return response()->json([
        'status' => true,
        'message' => 'تم إيقاف الإنتاج بنجاح',
        'data' => [
            'id' => $order->id,
            'status' => $order->status,
            
        ]
    ]);
}


public function preview($id)
{
    $data = $this->service->preview($id);

    return response()->json([
        'status' => true,
        'data' => $data
    ]);
}

public function resume($id, ResumeProductionOrderRequest $request)
{
    $order = $this->service->resume($id);

    return response()->json([
        'status' => true,
        'message' => 'تم استئناف الإنتاج',
        'data' => [
            'id' => $order->id,
            'status' => $order->status,
           
            
        ]
    ]);
}

public function complete($id, CompleteProductionOrderRequest $request)
{
    $order = $this->service->complete($id, $request->validated());

    return response()->json([
        'status' => true,
        'message' => 'تم إنهاء الإنتاج',
        'data' => [
            'id' => $order->id,
            'status' => $order->status,
            
        ]
    ]);
}


public function sendToProduction(
    SendMaterialsToProductionRequest $request,
    $id
)
{
    $order = $this->service->sendToProduction(
        $id,
        $request->validated()
    );

    return response()->json([
        'status' => true,
        'message' => 'Materials sent to production successfully',
        'data' => $order
    ]);
}

public function materialRequests(
    MaterialRequestsRequest $request
)
{
    $data = $this->service->materialRequests();

    return response()->json([

        'status' => true,

        'data' => [

            /*
            ==========================================
            1️⃣ الطلبات الجديدة
            ==========================================
            */
            'new_requests' => collect(
                $data['new_requests']
            )->map(function ($order) {

                $boms = \App\Models\BOM::with(
                    'basicItem.section'
                )
                ->where('final_item_id', $order->item_id)
                ->get();

                return [

                    'order_id' => $order->id,

                    'product_name' =>
                        $order->item->name ?? null,

                    'product_section' =>
                        $order->item->section->ar_name ?? null,

                    'required_quantity' =>
                        $order->quantity,

                    'status' => $order->status,

                  
                ];
            }),

            /*
            ==========================================
            2️⃣ قيد التحضير
            ==========================================
            */
            'preparing' => collect(
                $data['preparing']
            )->map(function ($order) {

                return [

                    'order_id' => $order->id,

                    'product_name' =>
                        $order->item->name ?? null,

                    'product_section' =>
                        $order->item->section->ar_name ?? null,

                    'required_quantity' =>
                        $order->quantity,

                    'status' => $order->status,

                    'materials' =>
                        $order->materials->map(function ($material) {

                            return [

                                'material_name' =>
                                    $material->item->name ?? null,

                                'material_section' =>
                                    $material->item->section->ar_name ?? null,

                                'required_quantity' =>
                                    $material->required_quantity,

                                'consumed_quantity' =>
                                    $material->consumed_quantity,

                                'batch' => [

                                    'batch_id' =>
                                        $material->shipmentItem->id ?? null,

                                    'taken_quantity' =>
                                        $material->consumed_quantity,

                                    'batch_quantity_before' =>
                                        ($material->shipmentItem->quantity_received ?? 0)
                                        + $material->consumed_quantity,

                                    'remaining_in_batch' =>
                                        $material->shipmentItem->quantity_received ?? 0,

                                    'received_at' =>
                                        $material->shipmentItem->created_at ?? null,

                                    'expiry_date' =>
                                        $material->shipmentItem->expiry_date ?? null,
                                ],
                            ];
                        }),
                ];
            }),

            /*
            ==========================================
            3️⃣ تم التسليم للإنتاج
            ==========================================
            */
            'delivered' => collect(
                $data['delivered']
            )->map(function ($order) {

                return [

                    'order_id' => $order->id,

                    'product_name' =>
                        $order->item->name ?? null,

                    'product_section' =>
                        $order->item->section->ar_name ?? null,

                    'required_quantity' =>
                        $order->quantity,

                    'produced_quantity' =>
                        $order->produced_quantity,

                    'status' => $order->status,

                    'materials' =>
                        $order->materials->map(function ($material) {

                            return [

                                'material_name' =>
                                    $material->item->name ?? null,

                                'material_section' =>
                                    $material->item->section->ar_name ?? null,

                                'consumed_quantity' =>
                                    $material->consumed_quantity,

                                'batch' => [

                                    'batch_id' =>
                                        $material->shipmentItem->id ?? null,

                                    'taken_quantity' =>
                                        $material->consumed_quantity,

                                    'received_at' =>
                                        $material->shipmentItem->created_at ?? null,

                                    'expiry_date' =>
                                        $material->shipmentItem->expiry_date ?? null,
                                ],
                            ];
                        }),

                    'delivered_at' =>
                        $order->updated_at,
                ];
            }),
        ]
    ]);
}


public function allProductionOrders(
    AllProductionOrdersRequest $request
)
{
    return response()->json([

        'status' => true,

        'data' => $this->service
            ->allProductionOrders()

    ]);
}
}