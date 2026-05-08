<?php

namespace App\Services\Production;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderMaterial;
use App\Models\Item;
use App\Models\BOM;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductionOrderLog;
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
     * 🏭 إنشاء أمر إنتاج
     */
    public function store(array $data)
    {
        // 🔍 جلب المادة
        $item = Item::findOrFail($data['item_id']);

        // ❌ مادة أولية
        if ($item->is_raw_material) {
            throw ValidationException::withMessages([
                'item_id' => ["المادة '{$item->name}' هي مادة أولية ولا يمكن إنشاء أمر إنتاج لها."]
            ]);
        }

        // ❌ ما عنده BOM
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
     * 👨‍💼 موافقة المدير
     */
   

   public function managerDecision($id, array $data)
{
    $order = ProductionOrder::findOrFail($id);

    // لازم يكون pending
    if ($order->status !== 'pending') {
        throw ValidationException::withMessages([
            'status' => 'لا يمكن اتخاذ قرار على طلب غير قيد الانتظار'
        ]);
    }

    $status = $data['status']; // approved / rejected

    if (!in_array($status, ['approved', 'rejected'])) {
        throw ValidationException::withMessages([
            'status' => 'الحالة يجب أن تكون approved أو rejected'
        ]);
    }

    $order->update([
        'status' => $status === 'approved'
            ? 'approved_by_manager'
            : 'rejected_by_manager',

        'notes' => $data['notes'] ?? $order->notes,
    ]);
    $this->log(
    $order,
    $status === 'approved'
        ? ProductionLogAction::MANAGER_APPROVED->value
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
        if ($order->status !== 'approved_by_manager') {
            throw ValidationException::withMessages([
                'status' => 'يجب موافقة المدير أولاً'
            ]);
        }

        /*
        ============================================
        1️⃣ جلب BOM
        ============================================
        */
        $boms = BOM::where('final_item_id', $order->item_id)->get();

        if ($boms->isEmpty()) {
            throw ValidationException::withMessages([
                'bom' => 'لا يوجد BOM لهذه المادة'
            ]);
        }

        /*
        ============================================
        2️⃣ تحقق من توفر المخزون
        ============================================
        */
        foreach ($boms as $bom) {

            $required = $bom->basic_item_quantity * $order->quantity;

            $available = ShipmentItem::where('item_id', $bom->basic_item_id)
                ->sum('quantity_received');

            if ($available < $required) {
                throw ValidationException::withMessages([
                    'stock' => "المخزون غير كافي للمادة رقم {$bom->basic_item_id}"
                ]);
            }
        }

        /*
        ============================================
        3️⃣ صرف المواد FIFO + تسجيل كل دفعة
        ============================================
        */
        foreach ($boms as $bom) {

            $required = $bom->basic_item_quantity * $order->quantity;

            // 📦 ترتيب حسب الصلاحية
            $batches = ShipmentItem::where('item_id', $bom->basic_item_id)
                ->where('quantity_received', '>', 0)
                ->orderBy('expiry_date')
                ->orderBy('created_at')
                ->get();

            foreach ($batches as $batch) {

                if ($required <= 0) break;

                $available = $batch->quantity_received;
                $deduct = min($available, $required);

                if ($deduct > 0) {

                    /*
                    🔻 1. خصم من المخزون
                    */
                    $batch->decrement('quantity_received', $deduct);

                    /*
                    🔻 2. تسجيل الصرف (من أي دفعة)
                    */
                    ProductionOrderMaterial::create([
                        'production_order_id' => $order->id,
                        'item_id' => $bom->basic_item_id,
                        'shipment_item_id' => $batch->id,
                        'required_quantity' => $bom->basic_item_quantity * $order->quantity,
                        'consumed_quantity' => $deduct,
                    ]);

                    $required -= $deduct;
                }
            }

            /*
            🔴 تحقق أمان
            */
            if ($required > 0) {
                throw ValidationException::withMessages([
                    'stock' => 'فشل في خصم المخزون بالكامل'
                ]);
            }
        }

        /*
        ============================================
        4️⃣ تحديث الحالة
        ============================================
        */
        $order->update([
            'status' => 'materials_reserved', // 🔥 أهم نقطة
            'warehouse_approved_by' => auth()->id(),
            'warehouse_approved_at' => now(),
            'notes' => $data['notes'] ?? $order->notes,
        ]);
         $this->log($order, 'warehouse_reserved');

        return $order;
    });
}



    public function start($id)
{
    $order = ProductionOrder::findOrFail($id);

    // ❌ لازم يكون جاهز للإنتاج
    if ($order->status !== 'ready_to_start') {
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
        'status' => 'in_production',
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

        // ❌ لازم يكون قيد الإنتاج
        if ($order->status !== 'in_production') {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن إيقاف طلب غير مبدوء'
            ]);
        }

        // 📦 إذا في إنتاج جزئي → خزّنه (UPDATE فقط)
        if (!empty($data['produced_quantity']) && $data['produced_quantity'] > 0) {

            // 🔎 جيب سجل موجود لهالمادة
            $shipmentItem = ShipmentItem::where('item_id', $order->item_id)->first();

            // ❌ إذا ما في سجل
            if (!$shipmentItem) {
                throw ValidationException::withMessages([
                    'stock' => 'لا يوجد سجل مخزون لهذه المادة لتحديثه'
                ]);
            }

           ShipmentItem::where('item_id', $order->item_id)
    ->increment('quantity_received', $data['produced_quantity']);
        }

        // ⏸ إيقاف الطلب
        $order->update([
            'status' => 'paused',
            // 'paused_by' => auth()->id(),
            'paused_at' => now(),
        ]);
$this->log($order, 'pause');
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
        'status' => 'in_production',
        'resumed_at' => now(),
        // 'resumed_by' => auth()->id(),
    ]);
    
 $this->log($order, 'resume');

    return $order;
}


public function complete($id, array $data)
{
    return DB::transaction(function () use ($id, $data) {

        $order = ProductionOrder::findOrFail($id);

        if (!in_array($order->status, ['in_production', 'paused'])) {
            throw ValidationException::withMessages([
                'status' => 'غير صالح للإكمال'
            ]);
        }

        $produced = (int) $data['produced_quantity'];

        if ($produced <= 0) {
            throw ValidationException::withMessages([
                'produced_quantity' => 'يجب أكبر من 0'
            ]);
        }

        if ($order->produced_quantity + $produced > $order->quantity) {
            throw ValidationException::withMessages([
                'produced_quantity' => 'زيادة غير مسموحة'
            ]);
        }

        $order->increment('produced_quantity', $produced);

        // تحديث مخزون المنتج النهائي
        ShipmentItem::updateOrCreate(
            ['item_id' => $order->item_id],
            ['quantity_received' => DB::raw("quantity_received + $produced")]
        );

        if ($order->produced_quantity >= $order->quantity) {

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $this->log($order, 'complete', $produced);

        } else {

            $this->log($order, 'partial_complete', $produced);

            $order->update([
                'status' => 'paused'
            ]);
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
    if ($order->status !== 'materials_reserved') {

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
        'status' => 'ready_to_start',
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
        'new_requests' => ProductionOrder::where(
            'status',
            'approved_by_manager'
        )->latest()->get(),

        'preparing' => ProductionOrder::where(
            'status',
            'materials_reserved'
        )->latest()->get(),

        'delivered' => ProductionOrder::where(
            'status',
            'ready_to_start'
        )->latest()->get(),
    ];
}
}