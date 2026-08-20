<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_order_id')->constrained('distribution_orders')->onDelete('cascade');
            $table->foreignId('distribution_order_item_id')->constrained('distribution_order_items')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('shipment_item_id')->constrained('shipment_items')->comment('الدفتة المحددة المأخوذ منها الصنف بناء على FEFO');
           // $table->decimal('allocated_quantity', 12, 3);
            $table->decimal('allocated_quantity', 16, 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_order_materials');
    }
};