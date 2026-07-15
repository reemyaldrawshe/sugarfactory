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
            $table->decimal('quantity', 12, 3)->comment('الكمية المطلوبة بالطن مثلاً');
            
            // 💡 تثبيت سعر البيع التاريخي لحساب الأرباح بدقة للمالية
            $table->decimal('price_per_ton', 15, 2)->default(0.00)->comment('سعر الطن في لحظة البيع الفعلي');
            $table->decimal('total_price', 15, 2)->default(0.00)->comment('إجمالي سعر الأسطر (الكمية × السعر)');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_order_items');
    }
};