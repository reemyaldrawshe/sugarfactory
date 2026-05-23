<?php


namespace App\Services\Warehouse;

use App\Models\Shipment;
use App\Models\ShipmentItem;

class ShipmentService
{
    public function store(array $data): Shipment
    {
        $shipment = Shipment::create([
            'supplier' => $data['supplier'] ?? null,
            'received_at' => $data['received_at'],
        ]);

      foreach ($data['items'] as $item) {

    // 1️⃣ إنشاء عنصر الشحنة وحفظه
    $shipmentItem = ShipmentItem::create([
        'shipment_id' => $shipment->id,
        'item_id' => $item['item_id'],
        'quantity' => $item['quantity'],
        'price' => $item['price'],
        'expiry_date' => $item['expiry_date'] ?? null,
        'note' => $item['note'] ?? null,
    ]);

}
        return $shipment;
    }
}
