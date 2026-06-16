<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderMaterial extends Model
{
    protected $fillable = [
        'production_order_id',
        'item_id',
        'shipment_item_id',
        'required_quantity',
        'consumed_quantity',
    ];

    protected $with = ['shipmentItem'];
    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function shipmentItem()
    {
    return $this->belongsTo(ShipmentItem::class);
    }
}
