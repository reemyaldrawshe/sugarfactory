<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distribution_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_order_id')->constrained('distribution_orders')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->comment('المنتج النهائي المباع');
           // 💡 توحيد كمية المنتج النهائي لتكون بنفس دقة بقية جداول النظام (16, 6)
            $table->decimal('quantity', 16, 6)->comment('الكمية المطلوبة (بالطن أو الكيلو)');
            
            // 💡 تثبيت سعر الوحدة التاريخي بدقة 4 خانات عشرية لحساب الأرباح والكسور بدقة
            $table->decimal('price_per_ton', 16, 4)->default(0.0000)->comment('سعر الطن/الوحدة في لحظة البيع الفعلي');
            $table->decimal('total_price', 16, 4)->default(0.0000)->comment('إجمالي سعر البند (الكمية × سعر الوحدة)');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_order_items');
    }
};