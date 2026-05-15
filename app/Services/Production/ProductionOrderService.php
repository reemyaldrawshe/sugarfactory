<?php

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\Item;
use App\Models\ProductionOrderLog;
use App\Models\BOM;
use App\Models\Shipment;
use App\Models\Section;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Enums\ProductionLogAction;


class ProductionOrderService
{

private function log($order, $action, $quantity = null, $meta = [])
{
    ProductionOrderLog::create([
        'production_order_id' => $order->id,
        'user_id' => auth()->id(),
        'action' => $action,
       
    ]);
}


    /**
     */
    public function store(array $data)
    {
        // جلب المادة
        $item = Item::findOrFail($data['item_id']);

        //  مادة أولية
        if ($item->is_raw_material == 0) {
            throw ValidationException::withMessages([
                'item_id' => ["المادة '{$item->name}' هي مادة أولية ولا يمكن إنشاء أمر إنتاج لها."]
            ]);
        }

        //  ما عنده BOM
        $bomExists = BOM::where('final_item_id', $item->id)->exists();

        if (!$bomExists) {
            throw ValidationException::withMessages([
                'item_id' => ["لا يمكن إنتاج '{$item->name}' لأنه لا يحتوي على مكونات (BOM)."]
            ]);
        }

        // ✅ إنشاء أمر الإنتاج
        $order = ProductionOrder::create([
            'item_id' => $item->id,
            'quantity' => $data['quantity'],
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
$this->log($order, \App\Enums\ProductionLogAction::CREATED->value);
        return $order;
    }

    /**
  
     */
   

  public function managerDecision($id, array $data)
{
    $order = ProductionOrder::findOrFail($id);

    /*
    |--------------------------------------------------------------------------
    | لازم يكون الطلب قيد الانتظار
    |--------------------------------------------------------------------------
    */
    if ($order->status !== 'pending') {

        throw ValidationException::withMessages([
            'status' => 'لا يمكن اتخاذ قرار على طلب غير قيد الانتظار'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | الحالة المطلوبة
    |--------------------------------------------------------------------------
    */
    $status = $data['status'];

    if (!in_array($status, ['approved', 'rejected'])) {

        throw ValidationException::withMessages([
            'status' => 'الحالة يجب أن تكون approved أو rejected'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | تحديث الحالة
    |--------------------------------------------------------------------------
    */
    $order->update([

        'status' => $status === 'approved'
            ? 'materials_reserved'
            : 'rejected_by_manager',

        'notes' =>
            $data['notes']
            ?? $order->notes,
    ]);

    /*
    |--------------------------------------------------------------------------
    | تسجيل Log
    |--------------------------------------------------------------------------
    */
    $this->log(

        $order,

        $status === 'approved'
            ? ProductionLogAction::MATERIALS_RESERVED->value
            : ProductionLogAction::MANAGER_REJECTED->value
    );

    return $order;
}




public function warehouseApprove($id, array $data)
{
    return DB::transaction(function () use ($id, $data) {

        $order = ProductionOrder::findOrFail($id);

        /*
        ============================================
        0️⃣ تحقق الحالة
        ============================================
        */
        if ($order->status !== 'materials_reserved') {
            throw ValidationException::withMessages([
                'status' => 'يجب موافقة المدير أولاً'
            ]);
        }

        /*
        ============================================
        1️⃣ جلب BOM
        ============================================
        */
        $boms = BOM::where(
            'final_item_id',
            $order->item_id
        )->get();

        if ($boms->isEmpty()) {
            throw ValidationException::withMessages([
                'bom' => 'لا يوجد BOM لهذه المادة'
            ]);
        }

        /*
        ============================================
        2️⃣ تحقق من توفر المخزون
        (فقط دفعات approved_lab)
        ============================================
        */
        foreach ($boms as $bom) {

            $required =
                $bom->basic_item_quantity
                * $order->quantity;

            $available = ShipmentItem::where(
                    'item_id',
                    $bom->basic_item_id
                )

                ->where('quantity_received', '>', 0)

                ->whereHas('shipment', function ($q) {
                    $q->where(
                        'status',
                        \App\Enums\ShipmentStatus::APPROVED_LAB->value
                    );
                })

                ->sum('quantity_received');

            if ($available < $required) {

                throw ValidationException::withMessages([

                    'stock' =>
                        "المخزون غير كافي للمادة رقم {$bom->basic_item_id}"
                ]);
            }
        }

        /*
        ============================================
        3️⃣ صرف المواد FIFO
        (حسب الصلاحية + approved_lab فقط)
        ============================================
        */
        foreach ($boms as $bom) {

            $required =
                $bom->basic_item_quantity
                * $order->quantity;

            /*
            ============================================
            📦 جلب الدفعات المسموحة
            ============================================
            */
            $batches = ShipmentItem::where(
                    'item_id',
                    $bom->basic_item_id
                )

                ->where('quantity_received', '>', 0)

                ->whereHas('shipment', function ($q) {
                    $q->where(
                        'status',
                        \App\Enums\ShipmentStatus::APPROVED_LAB->value
                    );
                })

                ->orderBy('expiry_date')
                ->orderBy('created_at')
                ->get();

            foreach ($batches as $batch) {

                if ($required <= 0) {
                    break;
                }

                $available =
                    $batch->quantity_received;

                $deduct = min(
                    $available,
                    $required
                );

                if ($deduct > 0) {

                    /*
                    ============================================
                    🔻 خصم من المخزون
                    ============================================
                    */
                    $batch->decrement(
                        'quantity_received',
                        $deduct
                    );

                    /*
                    ============================================
                    🧾 تسجيل الصرف
                    ============================================
                    */
                    ProductionOrderMaterial::create([

                        'production_order_id' =>
                            $order->id,

                        'item_id' =>
                            $bom->basic_item_id,

                        'shipment_item_id' =>
                            $batch->id,

                        'required_quantity' =>
                            $bom->basic_item_quantity
                            * $order->quantity,

                        'consumed_quantity' =>
                            $deduct,
                    ]);

                    $required -= $deduct;
                }
            }

            /*
            ============================================
            🔴 تحقق أمان
            ============================================
            */
            if ($required > 0) {

                throw ValidationException::withMessages([

                    'stock' =>
                        'فشل في خصم المخزون بالكامل'
                ]);
            }
        }

        /*
        ============================================
        4️⃣ تحديث الحالة
        ============================================
        */
        $order->update([

            'status' => 'sent_to_production',

            'warehouse_approved_by' =>
                auth()->id(),

            'warehouse_approved_at' =>
                now(),

            'notes' =>
                $data['notes']
                ?? $order->notes,
        ]);

        /*
        ============================================
        📝 تسجيل Log
        ============================================
        */
        $this->log(
            $order,
            ProductionLogAction::MATERIALS_RESERVED->value
        );

        return $order;
    });
}


    public function start($id)
{
    $order = ProductionOrder::findOrFail($id);

    // ❌ لازم يكون جاهز للإنتاج
    if ($order->status !== 'started') {
        throw ValidationException::withMessages([
            'status' => 'لا يمكن بدء الإنتاج إلا بعد موافقة المستودع'
        ]);

    }

    // ❌ إذا بلش قبل
    if ($order->started_at !== null) {
        throw ValidationException::withMessages([
            'status' => 'تم بدء الإنتاج مسبقاً'
        ]);
    }

    $order->update([
        'status' => 'started',
        'started_at' => now(),
        // 'started_by' => auth()->id(),
    ]);
      $this->log($order, 'start');

    return $order;
}


public function pause($id, array $data)
{
    return DB::transaction(function () use ($id, $data) {

        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'started') {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن إيقاف طلب غير مبدوء'
            ]);
        }

        if (!empty($data['produced_quantity']) && $data['produced_quantity'] > 0) {

            $produced = (int) $data['produced_quantity'];

            $remaining = $order->quantity - $order->produced_quantity;

            if ($produced > $remaining) {
                throw ValidationException::withMessages([
                    'produced_quantity' => "الكمية المتبقية فقط $remaining"
                ]);
            }

            // تحديث المخزون
            $shipmentItem = ShipmentItem::where('item_id', $order->item_id)->first();

            if (!$shipmentItem) {
                throw ValidationException::withMessages([
                    'stock' => 'لا يوجد سجل مخزون لهذه المادة لتحديثه'
                ]);
            }

            ShipmentItem::where('item_id', $order->item_id)
                ->increment('quantity_received', $produced);

            // تحديث الإنتاج
            $order->increment('produced_quantity', $produced);
        }

        // إعادة تحميل القيم بعد التحديث
        $order->refresh();

        // 🎯 القرار النهائي للحالة
        if ($order->produced_quantity >= $order->quantity) {

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
                'paused_at' => null,
            ]);

            $this->log($order, 'auto_complete');

        } else {

            $order->update([
                'status' => 'paused',
                'paused_at' => now(),
            ]);

            $this->log($order, 'pause');
        }

        return $order;
    });
}



public function preview($id)
{
    $order = ProductionOrder::findOrFail($id);

    $boms = BOM::where('final_item_id', $order->item_id)->get();

    $materials = [];

    foreach ($boms as $bom) {

        $required = $bom->basic_item_quantity * $order->quantity;

        $available = ShipmentItem::where('item_id', $bom->basic_item_id)
            ->sum('quantity_received');

        $materials[] = [
            'item_id' => $bom->basic_item_id,
            'required' => $required,
            'available' => $available,
            'enough' => $available >= $required
        ];
    }

    return [
        'order_id' => $order->id,
        'product_id' => $order->item_id,
        'quantity' => $order->quantity,
        'status' => $order->status,
        'materials' => $materials
    ];
}


public function resume($id)
{
    $order = ProductionOrder::findOrFail($id);

    if ($order->status !== 'paused') {
        throw ValidationException::withMessages([
            'status' => 'الطلب ليس متوقف'
        ]);
    }

    $order->update([
        'status' => 'started',
        'resumed_at' => now(),
    
    ]);
    
 $this->log($order, 'resume');

    return $order;
}


public function complete($id, array $data)
{
    return DB::transaction(function () use ($id, $data) {

        $order = ProductionOrder::findOrFail($id);

        if (!in_array($order->status, ['paused', 'started'])) {
            throw ValidationException::withMessages([
                'status' => 'غير صالح للإكمال'
            ]);
        }

        $produced = (int) $data['produced_quantity'];

        if ($produced <= 0) {
            throw ValidationException::withMessages([
                'produced_quantity' => 'يجب أن تكون أكبر من 0'
            ]);
        }

        // المتبقي من الكمية
        $remaining = $order->quantity - $order->produced_quantity;

        if ($produced > $remaining) {
            throw ValidationException::withMessages([
                'produced_quantity' => "الكمية المتبقية فقط $remaining"
            ]);
        }

        // تحديث الإنتاج
        $order->increment('produced_quantity', $produced);

        // تحديث المخزون
        ShipmentItem::updateOrCreate(
            ['item_id' => $order->item_id],
            ['quantity_received' => DB::raw("quantity_received + $produced")]
        );

        // إذا اكتمل الطلب
        if ($order->fresh()->produced_quantity >= $order->quantity) {

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->log($order, 'complete', $produced);

        } else {

            $order->update([
                'status' => 'paused'
            ]);

            $this->log($order, 'partial_complete', $produced);
        }

        return $order;
    });
}



public function sendToProduction($id, array $data = [])
{
    $order = ProductionOrder::findOrFail($id);

    /*
    ===========================================
    1️⃣ Check Status
    ===========================================
    */
    if ($order->status !== 'sent_to_production') {

        throw ValidationException::withMessages([
            'status' => 'Materials are not reserved yet'
        ]);
    }

    /*
    ===========================================
    2️⃣ Update Status
    ===========================================
    */
    $order->update([
        'status' => 'started',
        'notes' => $data['notes'] ?? $order->notes,
    ]);

    /*
    ===========================================
    3️⃣ Log Action
    ===========================================
    */
    $this->log(
        $order,
        ProductionLogAction::SENT_TO_PRODUCTION->value,
        null,
        [
            'message' => 'Materials delivered to production department'
        ]
    );

    return $order;
}



public function materialRequests()
{
    return [

        /*
        ==========================================
        1️⃣ الطلبات الجديدة
        ==========================================
        */
        'new_requests' => ProductionOrder::with('item.section')
            ->where('status', 'approved_by_manager')
            ->latest()
            ->get(),

        /*
        ==========================================
        2️⃣ قيد التحضير
        ==========================================
        */
        'preparing' => ProductionOrder::with([
              'item.section',
    'materials.item.section',
    'materials.shipmentItem'
            ])
            ->where('status', 'materials_reserved')
            ->latest()
            ->get(),

       
        'delivered' => ProductionOrder::with([
                'item.section',
    'materials.item.section',
    'materials.shipmentItem'
            ])
            ->whereIn('status', [
                'sent_to_production'
            ])
            ->latest()
            ->get(),
    ];
}


public function allProductionOrders()
{
    return ProductionOrder::with([

        'item',
        'materials.item',
        'materials.shipmentItem',
        'logs.user',
        'item.section',
'materials.item.section',

    ])
    ->latest()
    ->get()
    ->map(function ($order) {

        return [

        
            'order_id' => $order->id,

            'product_name' =>
                $order->item->name ?? null,

            'required_quantity' =>
                $order->quantity,

            'produced_quantity' =>
                $order->produced_quantity,

            'remaining_quantity' =>
                $order->quantity
                - $order->produced_quantity,

            'status' => $order->status,

            'created_at' => $order->created_at->format('Y-m-d'),
'product_section' =>
    $order->item->section->ar_name ?? null,
        
            'materials' => $order->materials->map(function ($material) {

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

                        'remaining_in_batch' =>
                            $material->shipmentItem->quantity_received ?? null,

                        'received_at' =>
                            $material->shipmentItem->created_at->format('Y-m-d')?? null,

                        'expiry_date' =>
                            $material->shipmentItem->expiry_date ->format('Y-m-d')?? null,
                    ],
                ];
            }),

           
        ];
    });
}
}